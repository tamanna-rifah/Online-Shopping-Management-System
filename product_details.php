<?php
session_start();
require 'db.php';

// Get product ID from query string
if (!isset($_GET['id'])) {
    die("Invalid product.");
}
$product_id = (int)$_GET['id'];

// Fetch product details
$product_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();

if ($product_result->num_rows === 0) {
    die("Product not found.");
}
$product = $product_result->fetch_assoc();

// Check if user already reviewed this product
$check_stmt = $conn->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_email = ? LIMIT 1");
$check_stmt->bind_param("is", $product_id, $user_email);
$check_stmt->execute();
$already_reviewed = $check_stmt->get_result()->num_rows > 0;

// Handle review form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_reviewed) {
    $rating = (int)$_POST['rating'];
    $review_text = $conn->real_escape_string($_POST['review_text']);
    $insert_stmt = $conn->prepare("INSERT INTO reviews (product_id, user_email, rating, review_text) VALUES (?, ?, ?, ?)");
    $insert_stmt->bind_param("isis", $product_id, $user_email, $rating, $review_text);
    $insert_stmt->execute();
    $insert_stmt->close();
    $already_reviewed = true;
}

// Fetch all reviews for this product
$reviews_stmt = $conn->prepare("
    SELECT rating, review_text, created_at, user_email 
    FROM reviews 
    WHERE product_id = ? 
    ORDER BY created_at DESC
");
$reviews_stmt->bind_param("i", $product_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Details</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f4f9;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        h1 {
            text-align: center;
            color: #007BFF;
        }

        .product-info {
            margin-bottom: 30px;
        }

        .product-info img {
            max-width: 100%;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .product-info p {
            font-size: 16px;
            margin: 6px 0;
        }

        form {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #fdfdfd;
        }

        form label {
            font-weight: bold;
        }

        form select,
        form textarea {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
            border-radius: 6px;
            border: 1px solid #bbb;
            font-size: 14px;
        }

        form button {
            margin-top: 12px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            background: #007BFF;
            color: white;
            cursor: pointer;
        }

        form button:hover {
            background: #0056b3;
        }

        .reviews {
            margin-top: 30px;
        }

        .review-card {
            border-bottom: 1px solid #ddd;
            padding: 12px 0;
        }

        .review-card p {
            margin: 5px 0;
        }

        .review-card strong {
            color: #007BFF;
        }

        .review-card small {
            color: #666;
            font-size: 12px;
        }

        .message {
            text-align: center;
            padding: 20px;
            background: #e6ffe6;
            border: 1px solid #9f9;
            border-radius: 8px;
            color: #060;
            font-weight: bold;
        }

        .back-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 16px;
            background: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: 0.3s;
        }

        .back-btn:hover {
            background: #0056b3;
        }

        .top-left-btn {
            position: inline-block;
            top: 15px;
            left: 20px;
            background: #007BFF;
            color: white;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: 0.3s;
        }

        .top-left-btn:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="user_dashboard.php" class="top-left-btn">⬅</a>

        <h1><?= htmlspecialchars($product['name']) ?></h1>

        <div class="product-info">
            <?php if (!empty($product['image'])): ?>
                <img src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
            <?php endif; ?>
            <p><strong>Category:</strong> <?= htmlspecialchars($product['category']) ?></p>
            <p><strong>Price:</strong> Tk <?= htmlspecialchars($product['price']) ?></p>
        </div>

        <h2>⭐ Customer Reviews</h2>
        <div class="reviews">
            <?php if ($reviews_result->num_rows > 0): ?>
                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                    <div class="review-card">
                        <p><strong><?= htmlspecialchars($review['user_email']) ?></strong> rated ⭐<?= $review['rating'] ?>/5</p>
                        <p><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                        <small>Posted on <?= $review['created_at'] ?></small>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align:center; color:#888; font-style:italic;">No reviews yet.</p>
            <?php endif; ?>
        </div>

    </div>
</body>

</html>