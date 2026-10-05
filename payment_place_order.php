<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
start_auth_session();

require 'db.php';
require_once __DIR__ . '/delivery_helper.php';
require_once __DIR__ . '/sslcommerz_api.php';
require_once __DIR__ . '/sslcommerz_config.php';

if (!isset($_SESSION['user_email'])) {
    header('Location: user_login.php');
    exit();
}

$user_email = $_SESSION['user_email'];

if (!isset($_POST['csrf']) || !hash_equals($_SESSION['pay_csrf'] ?? '', (string) $_POST['csrf'])) {
    die("<p>Invalid request. <a href='cart.php'>Back to cart</a></p>");
}

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

$valid_methods = ['cod', 'online'];
$payment_method = strtolower(trim((string) ($_POST['payment_method'] ?? '')));
if (!in_array($payment_method, $valid_methods, true)) {
    die("<p>Please select a valid payment method. <a href='payment.php'>Back to payment</a></p>");
}

$placeholders = implode(',', array_fill(0, count($selected_items), '?'));
$sql = "
    SELECT c.id AS cart_id, c.product_id, p.name, p.price, c.quantity, (p.price*c.quantity) AS line_total
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_email = ? AND c.id IN ($placeholders)
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Database error: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

$types = 's' . str_repeat('i', count($selected_items));
$params = array_merge([$user_email], $selected_items);
$bind_names = [];
$bind_names[] = &$types;
foreach ($params as $key => $value) {
    $bind_names[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bind_names);

$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$items) {
    die("<p>No matching items found. <a href='cart.php'>Back to cart</a></p>");
}

$subtotal = 0.0;
foreach ($items as $item) {
    $subtotal += (float) $item['line_total'];
}

$tax_rate = 0.0;
$flat_shipping = 60.0;
$shipping = $subtotal > 0 ? $flat_shipping : 0.0;
$tax = $subtotal * $tax_rate;
$grand_total = $subtotal + $shipping + $tax;

$customer_name = '';
$customer_phone = '';
$customer_address = '';
$customer_city = '';
$customer_country = 'Bangladesh';

$user_stmt = $conn->prepare('SELECT name, phone, location FROM users WHERE email = ? LIMIT 1');
if ($user_stmt) {
    $user_stmt->bind_param('s', $user_email);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();

    if ($user) {
        $customer_name = trim((string) ($user['name'] ?? ''));
        $customer_phone = trim((string) ($user['phone'] ?? ''));
        $customer_address = trim((string) ($user['location'] ?? ''));
        $customer_city = $customer_address !== '' ? $customer_address : 'Dhaka';
    }
}

if ($customer_name === '') {
    $customer_name = $user_email;
}

$conn->begin_transaction();

try {
    $order_status = 'pending';
    $payment_status = 'pending';
    $verified = $payment_method === 'cod' ? 'No' : 'No';

    $insert_order = $conn->prepare("
    INSERT INTO orders (
        user_email,
        subtotal,
        shipping,
        grand_total,
        payment_method,
        payment_status,
        status,
        delivery_status,
        created_at
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, 'order_placed', NOW())
");
    if (!$insert_order) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }

    $insert_order->bind_param('sdddsss', $user_email, $subtotal, $shipping, $grand_total, $payment_method, $payment_status, $order_status);
    $insert_order->execute();
    $order_id = (int) $insert_order->insert_id;
    $insert_order->close();
    add_tracking_event(
    $conn,
    $order_id,
    'order_placed',
    'Order placed successfully.'
);

    $insert_item = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');
    if (!$insert_item) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }

    foreach ($items as $item) {
        $insert_item->bind_param('iiid', $order_id, $item['product_id'], $item['quantity'], $item['price']);
        $insert_item->execute();
    }
    $insert_item->close();

    $initial_transaction_id = $payment_method === 'cod' ? 'COD-' . $order_id : null;
    $payment_note = $payment_method === 'cod' ? 'Cash on Delivery order created.' : 'SSLCommerz payment initialized.';

    $insert_payment = $conn->prepare("
        INSERT INTO payments (
            order_id, user_email, method, payment_id, transaction_id, trx_id,
            payment_note, verified, amount, status,
            customer_name, customer_phone, customer_address, gateway_response, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    if (!$insert_payment) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }

    $empty_payment_id = null;
    $empty_trx_id = null;
    $empty_gateway_response = null;

    $insert_payment->bind_param(
        'isssssssdsssss',
        $order_id,
        $user_email,
        $payment_method,
        $empty_payment_id,
        $initial_transaction_id,
        $empty_trx_id,
        $payment_note,
        $verified,
        $grand_total,
        $payment_status,
        $customer_name,
        $customer_phone,
        $customer_address,
        $empty_gateway_response
    );
    $insert_payment->execute();
    $payment_row_id = (int) $insert_payment->insert_id;
    $insert_payment->close();

    if ($payment_method === 'cod') {
        $delete_stmt = $conn->prepare("DELETE FROM cart WHERE user_email = ? AND id IN ($placeholders)");
        if (!$delete_stmt) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        $delete_types = 's' . str_repeat('i', count($selected_items));
        $delete_params = array_merge([$user_email], $selected_items);
        $delete_bind = [];
        $delete_bind[] = &$delete_types;
        foreach ($delete_params as $key => $value) {
            $delete_bind[] = &$delete_params[$key];
        }
        call_user_func_array([$delete_stmt, 'bind_param'], $delete_bind);
        $delete_stmt->execute();
        $delete_stmt->close();

        $conn->commit();
        unset($_SESSION['pay_csrf']);

        header('Location: my_orders.php?order_placed=' . $order_id);
        exit();
    }

    $transaction_reference = 'ORD-' . $order_id . '-PAY-' . $payment_row_id;
    $callbackBase = sslcommerz_base_url();
    $create_response = sslcommerz_init_payment([
        'total_amount' => number_format($grand_total, 2, '.', ''),
        'currency' => 'BDT',
        'tran_id' => $transaction_reference,
        'success_url' => $callbackBase . '/payment_success.php?order_id=' . $order_id . '&payment_row_id=' . $payment_row_id,
        'fail_url' => $callbackBase . '/payment_fail.php?order_id=' . $order_id . '&payment_row_id=' . $payment_row_id,
        'cancel_url' => $callbackBase . '/payment_cancel.php?order_id=' . $order_id . '&payment_row_id=' . $payment_row_id,
        'ipn_url' => $callbackBase . '/payment_success.php?order_id=' . $order_id . '&payment_row_id=' . $payment_row_id . '&ipn=1',
        'cus_name' => $customer_name,
        'cus_email' => $user_email,
        'cus_add1' => $customer_address,
        'cus_city' => $customer_city !== '' ? $customer_city : 'Dhaka',
        'cus_country' => $customer_country,
        'cus_phone' => $customer_phone,
        'shipping_method' => 'NO',
        'product_name' => 'Order #' . $order_id,
        'product_category' => 'Online Shopping',
        'product_profile' => 'general',
        'value_a' => (string) $order_id,
        'value_b' => (string) $payment_row_id,
    ]);

    $gateway_payment_id = (string) ($create_response['sessionkey'] ?? $create_response['tran_id'] ?? '');
    $gateway_url = (string) ($create_response['GatewayPageURL'] ?? '');

    if ($gateway_payment_id === '' || $gateway_url === '') {
        throw new RuntimeException('SSLCommerz response is missing session or gateway URL.');
    }

    $gateway_response_json = json_encode($create_response, JSON_UNESCAPED_SLASHES);
    $update_payment = $conn->prepare("
        UPDATE payments
        SET payment_id = ?, transaction_id = ?, payment_note = ?, gateway_response = ?, updated_at = NOW()
        WHERE id = ? AND order_id = ?
    ");
    if (!$update_payment) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }

    $payment_note = 'SSLCommerz payment created. Redirecting customer to gateway.';
    $update_payment->bind_param(
        'ssssii',
        $gateway_payment_id,
        $transaction_reference,
        $payment_note,
        $gateway_response_json,
        $payment_row_id,
        $order_id
    );
    $update_payment->execute();
    $update_payment->close();

    $conn->commit();
    $_SESSION['sslcommerz_last_order_id'] = $order_id;
    $_SESSION['sslcommerz_last_payment_row_id'] = $payment_row_id;

    header('Location: ' . $gateway_url);
    exit();
} catch (Throwable $e) {
    $conn->rollback();

    if (isset($order_id) && $order_id > 0) {
        $safe_message = substr($e->getMessage(), 0, 240);
        $stmt = $conn->prepare("
            UPDATE orders o
            LEFT JOIN payments p ON p.order_id = o.id
            SET o.status = 'cancelled',
                o.payment_status = 'failed',
                p.status = 'failed',
                p.payment_note = ?,
                p.gateway_response = ?,
                p.updated_at = NOW()
            WHERE o.id = ?
        ");
        if ($stmt) {
            $response = json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
            $stmt->bind_param('ssi', $safe_message, $response, $order_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    http_response_code(500);
    echo '<p>Order failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . " <a href='cart.php'>Back to cart</a></p>";
}
