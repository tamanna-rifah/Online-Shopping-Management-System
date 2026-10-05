<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
start_auth_session();

require 'db.php';
require_once __DIR__ . '/sslcommerz_api.php';

function redirect_to_orders(string $query): void
{
    header('Location: my_orders.php?' . $query);
    exit();
}

function payment_request_value(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$payment_row_id = filter_input(INPUT_GET, 'payment_row_id', FILTER_VALIDATE_INT);

if (!$order_id || !$payment_row_id) {
    http_response_code(400);
    echo 'Invalid callback parameters.';
    exit();
}

$tranId = trim((string) payment_request_value('tran_id', ''));
$valId = trim((string) payment_request_value('val_id', ''));
$gatewayPaymentId = trim((string) payment_request_value('bank_tran_id', $tranId));
$reportedStatus = strtolower(trim((string) payment_request_value('status', 'VALID')));

$stmt = $conn->prepare('SELECT id, order_id, payment_id, amount FROM payments WHERE id = ? AND order_id = ? LIMIT 1');
$stmt->bind_param('ii', $payment_row_id, $order_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    http_response_code(404);
    echo 'Payment record not found.';
    exit();
}

if ($tranId === '') {
    $tranId = (string) ($payment['transaction_id'] ?? '');
}

if ($gatewayPaymentId === '') {
    $gatewayPaymentId = (string) ($payment['payment_id'] ?? $tranId);
}

try {
    $validation = $valId !== '' ? sslcommerz_validate_payment($valId) : [];
    $validatedStatus = strtolower((string) ($validation['status'] ?? $reportedStatus));
    $validatedAmount = (float) ($validation['amount'] ?? $payment['amount']);
    $validatedTranId = (string) ($validation['tran_id'] ?? $tranId);
    $validatedBankTranId = (string) ($validation['bank_tran_id'] ?? $gatewayPaymentId);

    $isSuccess = in_array($validatedStatus, ['valid', 'validated'], true);
    $paymentState = $isSuccess ? 'success' : 'failed';
    $orderStatus = $isSuccess ? 'paid' : 'cancelled';
    $verified = $isSuccess ? 'Yes' : 'No';
    $note = $isSuccess
        ? 'SSLCommerz payment completed successfully.'
        : 'SSLCommerz payment could not be validated.';
    $gatewayResponse = json_encode([
        'request' => $_REQUEST,
        'validation' => $validation,
    ], JSON_UNESCAPED_SLASHES);

    $conn->begin_transaction();

    $updatePayment = $conn->prepare("
        UPDATE payments
        SET payment_id = ?, transaction_id = ?, trx_id = ?, amount = ?, status = ?, verified = ?, payment_note = ?, gateway_response = ?, updated_at = NOW()
        WHERE id = ? AND order_id = ?
    ");
    $updatePayment->bind_param(
        'sssdssssii',
        $validatedBankTranId,
        $validatedTranId,
        $validatedBankTranId,
        $validatedAmount,
        $paymentState,
        $verified,
        $note,
        $gatewayResponse,
        $payment_row_id,
        $order_id
    );
    $updatePayment->execute();
    $updatePayment->close();

    $updateOrder = $conn->prepare('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?');
    $updateOrder->bind_param('ssi', $orderStatus, $paymentState, $order_id);
    $updateOrder->execute();
    $updateOrder->close();

    if ($isSuccess && isset($_SESSION['user_email'])) {
        $user_email = $_SESSION['user_email'];
        $cart_delete = $conn->prepare("
            DELETE c
            FROM cart c
            INNER JOIN order_items oi ON oi.product_id = c.product_id
            WHERE c.user_email = ? AND oi.order_id = ?
        ");
        $cart_delete->bind_param('si', $user_email, $order_id);
        $cart_delete->execute();
        $cart_delete->close();
    }

    $conn->commit();
    unset($_SESSION['sslcommerz_last_order_id'], $_SESSION['sslcommerz_last_payment_row_id'], $_SESSION['pay_csrf']);

    if ($isSuccess) {
        redirect_to_orders('paid=' . $order_id);
    }

    redirect_to_orders('payment_failed=' . $order_id . '&reason=verify_not_completed');
} catch (Throwable $e) {
    $safe_message = substr($e->getMessage(), 0, 240);
    $response = json_encode(['error' => $e->getMessage(), 'request' => $_REQUEST], JSON_UNESCAPED_SLASHES);

    $stmt = $conn->prepare("
        UPDATE payments
        SET status = 'failed', payment_note = ?, gateway_response = ?, updated_at = NOW()
        WHERE id = ? AND order_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('ssii', $safe_message, $response, $payment_row_id, $order_id);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', payment_status = 'failed' WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $stmt->close();
    }

    redirect_to_orders('payment_failed=' . $order_id);
}
