<?php
// gateway_init.php
declare(strict_types=1);
session_start();
require 'db.php';

if (!isset($_SESSION['user_email'])) {
    header('Location: user_login.php');
    exit();
}
$user_email = $_SESSION['user_email'];

// must have snapshot from previous step
$snap = $_SESSION['checkout_snapshot'] ?? null;
if (!$snap || $snap['user_email'] !== $user_email) {
    echo "<p>Session expired. <a href='cart.php'>Back to cart</a></p>";
    exit();
}

$conn->begin_transaction();
try {
    // cast numbers
    $subtotal = (float)$snap['subtotal'];
    $shipping = (float)$snap['shipping'];
    $tax      = (float)$snap['tax'];
    $grand    = (float)$snap['grand_total'];

    // Insert order
    $stmt = $conn->prepare("INSERT INTO orders
      (user_email, subtotal, shipping, tax, grand_total, status, payment_method, created_at)
      VALUES (?, ?, ?, ?, ?, 'PENDING', 'ONLINE', NOW())");
    $stmt->bind_param("sdddd", $snap['user_email'], $subtotal, $shipping, $tax, $grand);
    if (!$stmt->execute()) {
        throw new Exception("Order insert failed: " . $stmt->error);
    }
    $order_id = (int)$stmt->insert_id;
    $stmt->close();

    // Insert items
    $oi = $conn->prepare("INSERT INTO order_items 
        (order_id, product_id, product_name, unit_price, quantity, line_total)
        VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($snap['items'] as $it) {
        $pid    = (int)$it['product_id'];
        $pname  = (string)$it['name'];
        $uprice = (float)$it['price'];
        $qty    = (int)$it['quantity'];
        $ltotal = (float)$it['line_total'];
        $oi->bind_param("iisidi", $order_id, $pid, $pname, $uprice, $qty, $ltotal);
        if (!$oi->execute()) {
            throw new Exception("Order item insert failed: " . $oi->error);
        }
    }
    $oi->close();
    $conn->commit();

    // ✅ SSLCOMMERZ Integration
    $tran_id = 'ORD' . str_pad((string)$order_id, 8, '0', STR_PAD_LEFT);
    $post_data = [
        'store_id'     => 'YOUR_STORE_ID',      // replace
        'store_passwd' => 'YOUR_STORE_PASSWD',  // replace
        'total_amount' => number_format($grand, 2, '.', ''),
        'currency'     => 'BDT',
        'tran_id'      => $tran_id,

        // success/fail/cancel pages (must exist)
        'success_url'  => "http://yourdomain.com/payment_success.php",
        'fail_url'     => "http://yourdomain.com/payment_fail.php",
        'cancel_url'   => "http://yourdomain.com/payment_cancel.php",

        // customer info
        'cus_name'     => $snap['shipping_address']['name'] ?? $user_email,
        'cus_email'    => $user_email,
        'cus_add1'     => $snap['shipping_address']['line1'] ?? '',
        'cus_city'     => $snap['shipping_address']['city'] ?? '',
        'cus_country'  => $snap['shipping_address']['country'] ?? 'Bangladesh',
        'cus_phone'    => $snap['shipping_address']['phone'] ?? '',

        // shipping info
        'ship_name'    => $snap['shipping_address']['name'] ?? $user_email,
        'ship_add1'    => $snap['shipping_address']['line1'] ?? '',
        'ship_city'    => $snap['shipping_address']['city'] ?? '',
        'ship_country' => $snap['shipping_address']['country'] ?? 'Bangladesh',
    ];

    // Send POST request to SSLCOMMERZ
    $ch = curl_init("https://sandbox.sslcommerz.com/gwprocess/v4/api.php"); // Sandbox URL
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200 || !$response) {
        throw new Exception("Failed to connect to SSLCOMMERZ");
    }

    $res = json_decode($response, true);
    if (isset($res['GatewayPageURL']) && $res['GatewayPageURL'] != "") {
        header("Location: " . $res['GatewayPageURL']);
        exit();
    } else {
        throw new Exception("SSLCOMMERZ error: " . $response);
    }
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo "<p>Failed to initialize payment. Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
