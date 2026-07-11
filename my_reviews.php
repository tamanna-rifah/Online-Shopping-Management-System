<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: user_login.php");
    exit();
}

$user_email = $_SESSION['user_email'];

// Fetch user reviews
$reviews_result = $conn->query("SELECT r.*, p.name 
                                FROM reviews r 
                                JOIN products p ON r.product_id = p.id
                                WHERE r.user_email='$user_email'");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f4f9;
            color: #333;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #007BFF;
        }

        .review-card {
            margin-bottom: 20px;
            padding: 20px;
            background: #fdfdfd;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease;
        }

        .review-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .review-card h3 {
            margin: 0 0 10px;
            font-size: 20px;
            color: #333;
        }

        .review-card p {
            margin: 6px 0;
            font-size: 15px;
        }

        .review-card small {
            display: block;
            margin-top: 8px;
            font-size: 12px;
            color: #666;
        }

        .no-reviews {
            text-align: center;
            padding: 30px;
            color: #888;
            font-style: italic;
            background: #fdfdfd;
            border-radius: 8px;
            border: 1px dashed #bbb;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>⭐ My Reviews</h1>
        <?php if ($reviews_result->num_rows > 0): ?>
            <?php while ($row = $reviews_result->fetch_assoc()): ?>
                <div class="review-card">
                    <h3><?= htmlspecialchars($row['name']) ?></h3>
                    <p><strong>Rating:</strong> ⭐ <?= $row['rating'] ?>/5</p>
                    <p><?= htmlspecialchars($row['review_text']) ?></p>
                    <small>Reviewed on <?= $row['created_at'] ?></small>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="no-reviews">You haven’t written any reviews yet.</p>
        <?php endif; ?>
    </div>
</body>

</html>