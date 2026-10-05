<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
start_auth_session();
require 'db.php';

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$payment_row_id = filter_input(INPUT_GET, 'payment_row_id', FILTER_VALIDATE_INT);
$gateway_payment_id = trim((string) ($_POST['bank_tran_id'] ?? $_GET['bank_tran_id'] ?? $_GET['paymentID'] ?? ''));

if (!$order_id || !$payment_row_id) {
    http_response_code(400);
    echo 'Invalid cancel callback.';
    exit();
}

$message = 'Customer cancelled online payment.';
$gateway_response = json_encode([
    'status' => 'cancel',
    'paymentID' => $gateway_payment_id,
    'query' => $_REQUEST,
], JSON_UNESCAPED_SLASHES);

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("
        UPDATE payments
        SET payment_id = COALESCE(NULLIF(?, ''), payment_id),
            status = 'failed',
            verified = 'No',
            payment_note = ?,
            gateway_response = ?,
            updated_at = NOW()
        WHERE id = ? AND order_id = ?
    ");
    $stmt->bind_param('sssii', $gateway_payment_id, $message, $gateway_response, $payment_row_id, $order_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', payment_status = 'failed' WHERE id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
}

unset($_SESSION['sslcommerz_last_order_id'], $_SESSION['sslcommerz_last_payment_row_id']);
header('Location: my_orders.php?payment_cancelled=' . $order_id);
exit();
