<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: user_login.php");
    exit();
}

$user_email = $_SESSION['user_email'];

// Load user's placed orders
$query = $conn->prepare("
    SELECT o.id AS order_id, o.status, o.grand_total, o.created_at, 
           GROUP_CONCAT(CONCAT(p.id, ':', p.name, ' (x', oi.quantity, ')') SEPARATOR ',') AS items
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.user_email = ? 
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$query->bind_param("s", $user_email);
$query->execute();
$orders = $query->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        h1 {
            text-align: center;
            margin: 20px;
            color: #333;
        }

        .orders {
            max-width: 900px;
            margin: auto;
            padding: 20px;
        }

        .order {
            background: white;
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .order h2 {
            margin: 0 0 10px;
            font-size: 18px;
            color: #007BFF;
        }

        .status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-block;
        }

        .Pending {
            background: #f0ad4e;
            color: white;
        }

        .Processing {
            background: #6c757d;
            color: white;
        }

        .Paid {
            background: #0275d8;
            color: white;
        }

        .Shipped {
            background: #5bc0de;
            color: white;
        }

        .Delivered {
            background: #5cb85c;
            color: white;
        }

        .Cancelled {
            background: #d9534f;
            color: white;
        }

        .back-btn,
        .review-btn,
        .already {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            transition: 0.3s ease;
        }

        .back-btn {
            background: #007BFF;
            color: white;
            font-weight: bold;
        }

        .back-btn:hover {
            background: #0056b3;
        }

        .review-btn {
            background: #28a745;
            color: white;
        }

        .review-btn:hover {
            background: #1e7e34;
        }

        .already {
            background: #e6ffe6;
            border: 1px solid #9f9;
            color: #060;
        }
    </style>
</head>

<body>
    <h1>📦 My Orders</h1>
    <div style="text-align:center;">
        <a href="user_dashboard.php" class="back-btn">⬅ Back to Shop</a>
    </div>
    <div class="orders">
        <?php if ($orders->num_rows > 0): ?>
            <?php while ($order = $orders->fetch_assoc()): ?>
                <div class="order">
                    <h2>Order #<?= htmlspecialchars($order['order_id']) ?></h2>
                    <p><strong>Items:</strong>
                    <ul>
                        <?php
                        $items = explode(',', $order['items']);
                        foreach ($items as $item) {
                            list($pid, $pname) = explode(':', $item, 2);
                            echo '<li>' . htmlspecialchars($pname);

                            if (strtolower($order['status']) === 'delivered') {
                                // Check if user already reviewed this product in this order
                                $check = $conn->prepare("
                        SELECT id FROM reviews 
                        WHERE product_id = ? AND order_id = ? AND user_email = ? 
                        LIMIT 1
                    ");
                                $check->bind_param("iis", $pid, $order['order_id'], $user_email);
                                $check->execute();
                                $review_result = $check->get_result();

                                if ($review_result->num_rows > 0) {
                                    echo ' <span class="already">✔ Already Reviewed</span>';
                                } else {
                                    echo ' <a href="add_review.php?product_id=' . $pid . '&order_id=' . $order['order_id'] . '" class="review-btn">✍ Add Review</a>';
                                }
                            }

                            echo '</li>';
                        }
                        ?>
                    </ul>
                    </p>
                    <p><strong>Total Price:</strong> <?= htmlspecialchars(number_format($order['grand_total'], 2)) ?> BDT</p>
                    <p><strong>Status:</strong>
                        <?php $status = strtolower($order['status']); ?>
                        <span class="status <?= ucfirst($status) ?>"><?= ucfirst($status) ?></span>
                    </p>
                    <p><small>Placed on: <?= htmlspecialchars($order['created_at']) ?></small></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align:center; font-size:18px;">You haven’t placed any orders yet.</p>
        <?php endif; ?>
    </div>
</body>

</html>