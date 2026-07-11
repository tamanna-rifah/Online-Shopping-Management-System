<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: user_login.php");
    exit();
}

$user_email = $_SESSION['user_email'];

// Get product_id and order_id from URL
if (!isset($_GET['product_id'], $_GET['order_id'])) {
    die("Invalid request.");
}

$product_id = (int)$_GET['product_id'];
$order_id   = (int)$_GET['order_id'];

// Fetch product details
$product_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();
if ($product_result->num_rows === 0) {
    die("Product not found.");
}
$product = $product_result->fetch_assoc();

// Check if review already exists for this order
$check = $conn->prepare("
    SELECT id FROM reviews 
    WHERE product_id = ? AND order_id = ? AND user_email = ? 
    LIMIT 1
");
$check->bind_param("iis", $product_id, $order_id, $user_email);
$check->execute();
$review_result = $check->get_result();
$already_reviewed = $review_result->num_rows > 0;

// Handle review submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$already_reviewed) {
    $rating = (int)$_POST['rating'];
    $review_text = $_POST['review_text'];

    $stmt = $conn->prepare("
        INSERT INTO reviews (order_id, product_id, user_email, rating, review_text) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisis", $order_id, $product_id, $user_email, $rating, $review_text);
    $stmt->execute();
    $stmt->close();

    $already_reviewed = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review: <?= htmlspecialchars($product['name']) ?></title>
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
    </style>
</head>

<body>
    <div class="container">
        <h1>Review: <?= htmlspecialchars($product['name']) ?></h1>

        <?php if ($already_reviewed): ?>
            <div class="message">✔ Review Done.</div>
            <div style="text-align:center;">
                <a href="my_orders.php" class="back-btn">⬅ Back to My Orders</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <label>Rating:</label>
                <select name="rating" required>
                    <option value="">Select</option>
                    <option value="5">⭐⭐⭐⭐⭐</option>
                    <option value="4">⭐⭐⭐⭐</option>
                    <option value="3">⭐⭐⭐</option>
                    <option value="2">⭐⭐</option>
                    <option value="1">⭐</option>
                </select><br><br>

                <label>Review:</label>
                <textarea name="review_text" rows="4" placeholder="Write your review..." required></textarea><br><br>
                <button type="submit">Submit Review</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>