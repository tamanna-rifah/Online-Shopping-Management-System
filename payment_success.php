<?php
// payment_success.php — handle PSP return/webhook (success)
declare(strict_types=1);
session_start();
require 'db.php';

// In production, VERIFY the gateway signature/HMAC before trusting.
$order_id   = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$payment_id = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);
if (!$order_id || !$payment_id) { echo "Bad params"; exit(); }

$conn->begin_transaction();
try {
  // Update payment record
  $stmt = $conn->prepare("UPDATE payments SET status='succeeded', txn_id=CONCAT('DEMO-', id)
                          WHERE id=? AND order_id=?");
  $stmt->bind_param("ii", $payment_id, $order_id);
  $stmt->execute();
  $stmt->close();

  // Update order: paid + processing
  $stmt = $conn->prepare("UPDATE orders SET payment_status='paid', order_status='processing'
                          WHERE id=?");
  $stmt->bind_param("i", $order_id);
  $stmt->execute();
  $stmt->close();

  $conn->commit();
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500); echo "Failed to confirm payment.";
  exit();
}

header("Location: my_orders.php?paid={$order_id}");
exit();
