<?php

declare(strict_types=1);
session_start();
require 'db.php';

// ---------- Auth Guard ----------
if (!isset($_SESSION['user_email'])) {
    header('Location: user_login.php');
    exit();
}
$user_email = $_SESSION['user_email'];

// ---------- CSRF ----------
if (!isset($_POST['csrf']) || !hash_equals($_SESSION['pay_csrf'] ?? '', $_POST['csrf'])) {
    die("<p>Invalid request. <a href='cart.php'>Back to cart</a></p>");
}

// ---------- Validate selected items ----------
if (!isset($_POST['selected_items']) || !is_array($_POST['selected_items'])) {
    die("<p>No items selected. <a href='cart.php'>Back to cart</a></p>");
}

$selected_items = array_values(array_unique(array_filter(
    array_map(static fn($v) => filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), $_POST['selected_items']),
    static fn($v) => $v !== false
)));

if (!$selected_items) {
    die("<p>No valid items selected. <a href='cart.php'>Back to cart</a></p>");
}

// ---------- Validate payment method ----------
$valid_methods = ['cod', 'online'];
if (!isset($_POST['payment_method']) || !in_array($_POST['payment_method'], $valid_methods, true)) {
    die("<p>Please select a valid payment method. <a href='payment.php'>Back to payment</a></p>");
}
$payment_method = $_POST['payment_method'];

// ---------- Re-query selected items ----------
$placeholders = implode(',', array_fill(0, count($selected_items), '?'));
$sql = "SELECT c.id AS cart_id, c.product_id, p.name, p.price, c.quantity, (p.price*c.quantity) AS line_total 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_email = ? AND c.id IN ($placeholders)";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Database error: " . $conn->error);

// Bind params dynamically
$types = 's' . str_repeat('i', count($selected_items));
$params = array_merge([$user_email], $selected_items);
$bind_names[] = &$types;
foreach ($params as $key => $value) {
    $bind_names[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);

$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$items) die("<p>No matching items found. <a href='cart.php'>Back to cart</a></p>");

// ---------- Calculate totals ----------
$subtotal = 0.0;
foreach ($items as $r) $subtotal += (float)$r['line_total'];

$TAX_RATE = 0.0;
$FLAT_SHIPPING = 60.0;
$shipping = $subtotal > 0 ? $FLAT_SHIPPING : 0.0;
$tax = $subtotal * $TAX_RATE;
$grand_total = $subtotal + $shipping + $tax;

// ---------- Insert order ----------
$order_status = $payment_method === 'cod' ? 'Pending' : 'Awaiting Payment';
$insert_order = $conn->prepare(
    "INSERT INTO orders (user_email, grand_total, payment_method, status, created_at) VALUES (?, ?, ?, ?, NOW())"
);
if (!$insert_order) die("Prepare failed: " . $conn->error);

$insert_order->bind_param("sdss", $user_email, $grand_total, $payment_method, $order_status);
$insert_order->execute();
$order_id = $insert_order->insert_id;
if (!$order_id) die("Insert order failed: " . $insert_order->error);
$insert_order->close();

// ---------- Insert order items ----------
$insert_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
if (!$insert_item) die("Prepare failed: " . $conn->error);

foreach ($items as $r) {
    $insert_item->bind_param("iiid", $order_id, $r['product_id'], $r['quantity'], $r['price']);
    $insert_item->execute();
}
$insert_item->close();

// ---------- Remove purchased items from cart ----------
if ($selected_items) {
    $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
    $delete_stmt = $conn->prepare("DELETE FROM cart WHERE user_email = ? AND id IN ($placeholders)");
    if (!$delete_stmt) die("Prepare failed: " . $conn->error);

    $types = 's' . str_repeat('i', count($selected_items));
    $params = array_merge([$user_email], $selected_items);
    $bind_names = [];
    $bind_names[] = &$types;
    foreach ($params as $key => $value) {
        $bind_names[] = &$params[$key];
    }
    call_user_func_array([$delete_stmt, 'bind_param'], $bind_names);

    $delete_stmt->execute();
    $delete_stmt->close();
}

// ---------- Helper ----------
function bdt($n): string
{
    return number_format((float)$n, 2) . ' BDT';
}

// ---------- HTML (Order Confirmation) ----------
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }

        .wrapper {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 16px;
        }

        .card {
            background: #fff;
            padding: 20px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .06);
        }

        h1 {
            color: #28a745;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eef2f7;
        }

        .summary {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 6px 12px;
            margin-top: 12px;
        }

        .summary .label {
            color: #6b7280;
        }

        .summary .value {
            justify-self: end;
            font-weight: 700;
        }

        .btn {
            padding: 12px 18px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-success {
            background: #28a745;
            color: #fff;
        }

        .btn-primary {
            background: #007bff;
            color: #fff;
        }

        .status {
            padding: 8px 12px;
            border-radius: 12px;
            display: inline-block;
            font-weight: 700;
        }

        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .note {
            font-size: 13px;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="card">
            <h1>Thank You! Your Order is Processing</h1>
            <h2>Order #<?= htmlspecialchars((string)$order_id, ENT_QUOTES) ?></h2>
            <p>Payment Method: <strong><?= htmlspecialchars(strtoupper($payment_method), ENT_QUOTES) ?></strong> |
                Status: <span class="status <?= $payment_method === 'cod' ? 'status-pending' : 'status-paid' ?>"><?= htmlspecialchars($order_status, ENT_QUOTES) ?></span>
            </p>

            <table aria-label="Order Items">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></td>
                            <td><?= bdt((float)$item['price']) ?></td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td><?= bdt((float)$item['line_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="summary">
                <div class="label">Subtotal</div>
                <div class="value"><?= bdt($subtotal) ?></div>
                <div class="label">Shipping</div>
                <div class="value"><?= bdt($shipping) ?></div>
                <div class="label">VAT (0%)</div>
                <div class="value"><?= bdt($tax) ?></div>
                <div class="label" style="font-size:18px;">Grand Total</div>
                <div class="value" style="font-size:18px;"><?= bdt($grand_total) ?></div>
            </div>

            <div style="margin-top:20px;">
                <?php if ($payment_method === 'online'): ?>
                    <a class="btn btn-success" href="online_payment_gateway.php?order_id=<?= $order_id ?>&amount=<?= $grand_total ?>">Pay Now Online</a>
                    <a class="btn btn-primary" href="user_dashboard.php">Continue Shopping</a>

                <?php else: ?>
                    <a class="btn btn-primary" href="user_dashboard.php">Continue Shopping</a>
                <?php endif; ?>
            </div>
            <p class="note" style="margin-top:12px;">You will receive a confirmation email shortly.</p>
        </div>
    </div>
</body>

</html>