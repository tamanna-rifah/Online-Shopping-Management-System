<?php
session_start();
require 'db.php';

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if (!isset($_SESSION['user_email'])) {
    // Store the product ID in session for redirection after login
    $_SESSION['redirect_to_cart'] = true;
    $_SESSION['product_id'] = $product_id;
    header('Location: user_login.php');
    exit();
}

$user_email = $_SESSION['user_email'];

// Check if product is already in cart
$query = $conn->prepare("SELECT quantity FROM cart WHERE user_email = ? AND product_id = ?");
$query->bind_param("si", $user_email, $product_id);
$query->execute();
$query->store_result();

if ($query->num_rows > 0) {
    // Update quantity if product already in cart
    $update_query = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_email = ? AND product_id = ?");
    $update_query->bind_param("si", $user_email, $product_id);
    $update_query->execute();
} else {
    // Insert new product into cart
    $insert_query = $conn->prepare("INSERT INTO cart (user_email, product_id, quantity) VALUES (?, ?, 1)");
    $insert_query->bind_param("si", $user_email, $product_id);
    $insert_query->execute();
}

header('Location: cart.php');
exit();
?>