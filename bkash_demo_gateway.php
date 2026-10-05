<?php
declare(strict_types=1);
session_start();

require 'db.php';
require_once __DIR__ . '/bkash_api.php';

if (!isset($_SESSION['user_email'])) {
    header('Location: user_login.php');
    exit();
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$payment_row_id = filter_input(INPUT_GET, 'payment_row_id', FILTER_VALIDATE_INT);

if (!$order_id || !$payment_row_id) {
    http_response_code(400);
    echo 'Invalid demo payment request.';
    exit();
}

$stmt = $conn->prepare("
    SELECT o.id AS order_id, o.grand_total, p.id AS payment_row_id, p.amount, p.payment_id
    FROM orders o
    INNER JOIN payments p ON p.order_id = o.id
    WHERE o.id = ? AND p.id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $order_id, $payment_row_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$payment) {
    http_response_code(404);
    echo 'Payment not found.';
    exit();
}

$demo_payment_id = (string) ($payment['payment_id'] ?: bkash_demo_payment_id($order_id, $payment_row_id));
$missing_fields = bkash_missing_config_fields();
$is_demo_mode = !bkash_has_live_credentials();
$error_message = '';

$wallet = trim((string) ($_POST['wallet'] ?? ''));
$pin = trim((string) ($_POST['pin'] ?? ''));
$otp = trim((string) ($_POST['otp'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $wallet = '';
    $pin = '';
    $otp = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['demo_action'] ?? '');

    if ($action === 'cancel') {
        header('Location: payment_cancel.php?order_id=' . $order_id . '&payment_row_id=' . $payment_row_id . '&paymentID=' . urlencode($demo_payment_id) . '&demo=1');
        exit();
    }

    if (!preg_match('/^01[0-9]{9}$/', $wallet)) {
        $error_message = 'Valid wallet number dao.';
    } elseif (!preg_match('/^[0-9]{5}$/', $pin)) {
        $error_message = 'PIN 5 digit hote hobe.';
    } elseif (!preg_match('/^[0-9]{6}$/', $otp)) {
        $error_message = 'OTP 6 digit hote hobe.';
    } elseif ($action === 'pay') {
        if ($wallet === '01823074818') {
            header('Location: payment_fail.php?order_id=' . $order_id . '&payment_row_id=' . $payment_row_id . '&paymentID=' . urlencode($demo_payment_id) . '&demo=1');
            exit();
        }

        if ($wallet !== '01871775616') {
            $error_message = 'Demo successful wallet number holo 01871775616.';
        } elseif ($pin !== '1234') {
            $error_message = 'PIN thik na. Demo PIN holo 1234.';
        } elseif ($otp !== '1234') {
            $error_message = 'OTP thik na. Demo OTP holo 1234.';
        } else {
            header('Location: payment_success.php?order_id=' . $order_id . '&payment_row_id=' . $payment_row_id . '&paymentID=' . urlencode($demo_payment_id) . '&demo=1');
            exit();
        }
    } elseif ($action === 'fail') {
        header('Location: payment_fail.php?order_id=' . $order_id . '&payment_row_id=' . $payment_row_id . '&paymentID=' . urlencode($demo_payment_id) . '&demo=1');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>bKash Demo Checkout</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: linear-gradient(180deg, #fdf2f8 0%, #fff 100%); color: #1f2937; }
        .wrap { max-width: 560px; margin: 32px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 24px; box-shadow: 0 20px 45px rgba(0,0,0,.08); overflow: hidden; border: 1px solid #f3d7e6; }
        .top { background: linear-gradient(135deg, #e2136e, #c40f5e); color: #fff; padding: 24px; }
        .top h1 { margin: 0 0 6px; font-size: 26px; }
        .top p { margin: 0; opacity: .92; }
        .body { padding: 22px; }
        .alert { background: #fff7ed; color: #9a3412; border: 1px solid #fdba74; padding: 12px 14px; border-radius: 14px; margin-bottom: 16px; line-height: 1.5; }
        .error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 12px 14px; border-radius: 14px; margin-bottom: 16px; }
        .meta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 14px; margin-bottom: 16px; }
        .meta div { margin: 6px 0; }
        .field { margin-bottom: 14px; }
        label { display: block; font-weight: 700; margin-bottom: 6px; font-size: 15px; }
        input { width: 100%; padding: 14px 16px; border: 1px solid #d1d5db; border-radius: 14px; font-size: 17px; box-sizing: border-box; }
        input:focus { outline: none; border-color: #e2136e; box-shadow: 0 0 0 4px rgba(226,19,110,.12); }
        .hint { font-size: 13px; color: #6b7280; margin-top: 5px; line-height: 1.5; }
        .actions { display: grid; gap: 10px; margin-top: 18px; }
        button { border: none; border-radius: 14px; padding: 13px 16px; font-weight: 700; font-size: 15px; cursor: pointer; }
        .success { background: #e2136e; color: #fff; }
        .fail { background: #fee2e2; color: #b91c1c; }
        .cancel { background: #e5e7eb; color: #111827; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="top">
                <h1>bKash Checkout</h1>
                <p><?= $is_demo_mode ? 'Demo mode is active for local testing.' : 'Sandbox checkout' ?></p>
            </div>
            <div class="body">
                <?php if ($is_demo_mode): ?>
                    <div class="alert">
                        Real bKash credential paoa jai ni, tai demo checkout cholche.
                        Missing: <?= htmlspecialchars(implode(', ', $missing_fields), ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message !== ''): ?>
                    <div class="error"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="meta">
                    <div><strong>Order ID:</strong> #<?= (int) $order_id ?></div>
                    <div><strong>Payment ID:</strong> <?= htmlspecialchars($demo_payment_id, ENT_QUOTES, 'UTF-8') ?></div>
                    <div><strong>Amount:</strong> Tk <?= number_format((float) $payment['amount'], 2) ?></div>
                </div>

                <form method="post">
                    <div class="field">
                        <label for="wallet">Wallet Number</label>
                        <input id="wallet" name="wallet" value="<?= htmlspecialchars($wallet, ENT_QUOTES, 'UTF-8') ?>" placeholder="01XXXXXXXXX" inputmode="numeric" maxlength="11">
                        <div class="hint">Success test: `01871775616` | Blocked test: `01823074818`</div>
                    </div>

                    <div class="field">
                        <label for="pin">PIN</label>
                        <input id="pin" name="pin" type="password" value="<?= htmlspecialchars($pin, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter 5 digit PIN" inputmode="numeric" maxlength="5">
                        <div class="hint">Demo PIN: `1234`</div>
                    </div>

                    <div class="field">
                        <label for="otp">OTP</label>
                        <input id="otp" name="otp" value="<?= htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter 6 digit OTP" inputmode="numeric" maxlength="6">
                        <div class="hint">Demo OTP: `1234`</div>
                    </div>

                    <div class="actions">
                        <button class="success" type="submit" name="demo_action" value="pay">Pay Now</button>
                        <button class="fail" type="submit" name="demo_action" value="fail">Simulate Failure</button>
                        <button class="cancel" type="submit" name="demo_action" value="cancel">Cancel Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
