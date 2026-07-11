<?php
// pay_online.php — demo placeholder for a real payment gateway
declare(strict_types=1);
session_start();
require 'db.php';

$order_id   = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$payment_id = filter_input(INPUT_GET, 'payment_id', FILTER_VALIDATE_INT);
if (!$order_id || !$payment_id) { echo "Missing params"; exit(); }

// You could show a breakdown here, but it's optional for the demo.
?>
<!doctype html>
<meta charset="utf-8">
<title>Demo Online Payment</title>
<h1>Demo Online Payment</h1>
<p>Order #<?= (int)$order_id ?> • Payment #<?= (int)$payment_id ?></p>

<form action="payment_success.php" method="post" style="display:inline-block;margin-right:8px;">
  <input type="hidden" name="order_id" value="<?= (int)$order_id ?>">
  <input type="hidden" name="payment_id" value="<?= (int)$payment_id ?>">
  <button>Simulate Success</button>
</form>

<form action="payment_fail.php" method="post" style="display:inline-block;">
  <input type="hidden" name="order_id" value="<?= (int)$order_id ?>">
  <input type="hidden" name="payment_id" value="<?= (int)$payment_id ?>">
  <button>Simulate Fail</button>
</form>
