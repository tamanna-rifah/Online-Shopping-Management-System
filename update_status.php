<?php

declare(strict_types=1);
session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/db.php';

// CSRF check
if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
    die("Invalid CSRF token");
}

// Sanitize inputs
$orderId   = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$newStatus = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : '';

// Allow all valid statuses
$allowed = ['pending', 'processing', 'paid', 'shipped', 'delivered', 'cancelled'];

if ($orderId > 0 && in_array($newStatus, $allowed, true)) {
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $newStatus, $orderId);
    $stmt->execute();
    $stmt->close();
}

// Redirect back
header('Location: admin_dashboard.php');
exit();
