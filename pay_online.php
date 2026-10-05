<?php
declare(strict_types=1);
session_start();
require 'db.php';

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$payment_id = filter_input(INPUT_GET, 'payment_id', FILTER_VALIDATE_INT);
if (!$order_id || !$payment_id) {
    echo "Missing params";
    exit();
}

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.method,
        p.amount,
        p.status,
        p.transaction_id,
        p.sender_number,
        p.provider_account,
        p.account_name,
        p.card_last4,
        p.payment_note,
        o.user_email
    FROM payments p
    INNER JOIN orders o ON o.id = p.order_id
    WHERE p.id = ? AND p.order_id = ?
");
$stmt->bind_param("ii", $payment_id, $order_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    echo "Payment not found";
    exit();
}

$labelMap = [
    'online' => 'Online Payment',
    'cod' => 'Cash on Delivery',
];
$methodLabel = $labelMap[$payment['method']] ?? strtoupper((string) $payment['method']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Confirm Payment</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fb; color: #1f2937; margin: 0; padding: 32px 16px; }
        .card { max-width: 680px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.06); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 18px; margin-top: 16px; }
        .full { grid-column: 1 / -1; }
        .label { color: #64748b; font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .value { font-weight: 700; margin-top: 4px; }
        .btn { display: inline-block; text-decoration: none; border: none; padding: 12px 18px; border-radius: 12px; font-weight: 700; cursor: pointer; margin-right: 8px; margin-top: 18px; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="card">
        <h1><?= htmlspecialchars($methodLabel, ENT_QUOTES) ?> Confirmation</h1>
        <p>Review the payment details below. If everything is okay, confirm the payment.</p>

        <div class="grid">
            <div>
                <div class="label">Order</div>
                <div class="value">#<?= (int) $order_id ?></div>
            </div>
            <div>
                <div class="label">Payment</div>
                <div class="value">#<?= (int) $payment_id ?></div>
            </div>
            <div>
                <div class="label">Customer</div>
                <div class="value"><?= htmlspecialchars((string) $payment['user_email'], ENT_QUOTES) ?></div>
            </div>
            <div>
                <div class="label">Current Status</div>
                <div class="value"><?= htmlspecialchars((string) $payment['status'], ENT_QUOTES) ?></div>
            </div>
            <?php if (!empty($payment['sender_number'])): ?>
                <div>
                    <div class="label">Sender Number</div>
                    <div class="value"><?= htmlspecialchars((string) $payment['sender_number'], ENT_QUOTES) ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($payment['transaction_id'])): ?>
                <div>
                    <div class="label">Transaction ID</div>
                    <div class="value"><?= htmlspecialchars((string) $payment['transaction_id'], ENT_QUOTES) ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($payment['account_name'])): ?>
                <div>
                    <div class="label">Account Holder</div>
                    <div class="value"><?= htmlspecialchars((string) $payment['account_name'], ENT_QUOTES) ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($payment['card_last4'])): ?>
                <div>
                    <div class="label">Card Last 4</div>
                    <div class="value">**** <?= htmlspecialchars((string) $payment['card_last4'], ENT_QUOTES) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <form action="payment_success.php" method="post" style="display:inline-block;">
            <input type="hidden" name="order_id" value="<?= (int) $order_id ?>">
            <input type="hidden" name="payment_id" value="<?= (int) $payment_id ?>">
            <button class="btn btn-success">Confirm Payment</button>
        </form>

        <form action="payment_fail.php" method="post" style="display:inline-block;">
            <input type="hidden" name="order_id" value="<?= (int) $order_id ?>">
            <input type="hidden" name="payment_id" value="<?= (int) $payment_id ?>">
            <button class="btn btn-danger">Mark as Failed</button>
        </form>
    </div>
</body>
</html>
