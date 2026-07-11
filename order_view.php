<?php
declare(strict_types=1);
session_start();
require 'db.php';
if (!isset($_SESSION['user_email'])) { header('Location: user_login.php'); exit(); }
$user_email = $_SESSION['user_email'];

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) { echo "No order."; exit(); }

$stmt = $conn->prepare("SELECT * FROM orders WHERE id=? AND user_email=?");
$stmt->bind_param("is", $order_id, $user_email);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { echo "Order not found."; exit(); }

$it = $conn->prepare("SELECT name, unit_price, quantity, line_total FROM order_items WHERE order_id=?");
$it->bind_param("i", $order_id);
$it->execute();
$items = $it->get_result()->fetch_all(MYSQLI_ASSOC);
$it->close();
?>
<!doctype html>
<meta charset="utf-8">
<title>Order #<?= (int)$order_id ?></title>
<h1>Order #<?= (int)$order_id ?></h1>
<p>Payment: <?= htmlspecialchars($order['payment_method']) ?> (<?= htmlspecialchars($order['payment_status']) ?>)</p>
<p>Status: <?= htmlspecialchars($order['order_status']) ?></p>
<p>Date: <?= htmlspecialchars($order['created_at']) ?></p>

<table border="1" cellpadding="8" cellspacing="0">
  <tr><th>Product</th><th>Unit (BDT)</th><th>Qty</th><th>Line (BDT)</th></tr>
  <?php foreach ($items as $i): ?>
    <tr>
      <td><?= htmlspecialchars($i['name']) ?></td>
      <td><?= number_format((float)$i['unit_price'], 2) ?></td>
      <td><?= (int)$i['quantity'] ?></td>
      <td><?= number_format((float)$i['line_total'], 2) ?></td>
    </tr>
  <?php endforeach; ?>
  <tr><td colspan="3" align="right"><b>Subtotal</b></td><td><?= number_format((float)$order['subtotal'], 2) ?></td></tr>
  <tr><td colspan="3" align="right"><b>Shipping</b></td><td><?= number_format((float)$order['shipping'], 2) ?></td></tr>
  <tr><td colspan="3" align="right"><b>Tax</b></td><td><?= number_format((float)$order['tax'], 2) ?></td></tr>
  <tr><td colspan="3" align="right"><b>Grand Total</b></td><td><?= number_format((float)$order['grand_total'], 2) ?></td></tr>
</table>

<p><a href="my_orders.php">← Back to My Orders</a></p>
