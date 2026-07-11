<?php
// update_seller_status.php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/db.php';


/* Only POST requests allowed */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: sellers.php');

    exit();
}


/* CSRF verification */

$submittedCsrf =
    $_POST['csrf'] ?? '';

$sessionCsrf =
    $_SESSION['csrf'] ?? '';


if (
    !$sessionCsrf ||
    !hash_equals(
        $sessionCsrf,
        $submittedCsrf
    )
) {

    die('Invalid CSRF token.');
}


/* Get submitted values */

$sellerId =
    (int) ($_POST['seller_id'] ?? 0);

$status =
    strtolower(
        trim(
            (string)
            ($_POST['status'] ?? '')
        )
    );


/* Allowed seller statuses */

$allowedStatuses = [
    'approved',
    'rejected'
];


if (
    $sellerId <= 0 ||
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    header('Location: sellers.php');

    exit();
}


/* Update seller */

$stmt = $conn->prepare("
    UPDATE sellers
    SET status = ?
    WHERE id = ?
");

$stmt->bind_param(
    "si",
    $status,
    $sellerId
);

$stmt->execute();

$stmt->close();


/* Return to seller list */

header('Location: sellers.php');

exit();
