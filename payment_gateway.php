<?php

declare(strict_types=1);
session_start();
require 'db.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    echo "<p>Invalid order. <a href='cart.php'>Back to cart</a></p>";
    exit();
}

// Load order info
$stmt = $conn->prepare("SELECT grand_total, payment_method, status FROM orders WHERE id=?");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();

if (!$order || $order['payment_method'] !== 'online') {
    echo "<p>Order not found or not online payment. <a href='cart.php'>Back to cart</a></p>";
    exit();
}

// Simulate payment success after "Pay Now" click
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark order as paid
    $stmt = $conn->prepare("UPDATE orders SET status='Paid' WHERE id=?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();

    header("Location: order_success.php?order_id=$order_id");
    exit();
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Online Payment</title>
</head>

<body>
    <h2>Order #<?= $order_id ?> — Total: <?= number_format((float)$order['grand_total'], 2) ?> BDT</h2>
    <p>Simulated Online Payment Gateway</p>
    <form method="post">
        <button type="submit">Pay Now</button>
    </form>
    <a href="cart.php">Cancel</a>
</body>

</html>