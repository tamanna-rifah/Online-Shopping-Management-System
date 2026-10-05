<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
start_auth_session();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/delivery_helper.php';

if (!isset($_SESSION['user_email'])) {
    header('Location: user_login.php');
    exit();
}

$userEmail = $_SESSION['user_email'];

$orderId = (int) ($_GET['id'] ?? 0);

if ($orderId <= 0) {
    die('Invalid order.');
}

/*
|--------------------------------------------------------------------------
| Get order
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        o.*,
        COALESCE(u.name, o.user_email) AS customer_name,
        COALESCE(u.phone, '') AS customer_phone
    FROM orders o
    LEFT JOIN users u
        ON u.email = o.user_email
    WHERE o.id = ?
      AND o.user_email = ?
    LIMIT 1
");

$stmt->bind_param(
    'is',
    $orderId,
    $userEmail
);

$stmt->execute();

$order = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();

if (!$order) {
    die('Order not found.');
}

/*
|--------------------------------------------------------------------------
| Get tracking history
|--------------------------------------------------------------------------
*/

$historyStmt = $conn->prepare("
    SELECT *
    FROM order_tracking
    WHERE order_id = ?
    ORDER BY created_at ASC, id ASC
");

$historyStmt->bind_param(
    'i',
    $orderId
);

$historyStmt->execute();

$history = $historyStmt
    ->get_result()
    ->fetch_all(MYSQLI_ASSOC);

$historyStmt->close();

function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$steps = [
    'order_placed'     => 'Order Placed',
    'processing'       => 'Processing',
    'packed'           => 'Packed',
    'shipped'          => 'Shipped',
    'out_for_delivery' => 'Out for Delivery',
    'delivered'        => 'Delivered',
];

$currentStatus =
    (string) ($order['delivery_status'] ?? 'order_placed');

$currentIndex =
    array_search(
        $currentStatus,
        array_keys($steps),
        true
    );

if ($currentIndex === false) {
    $currentIndex = 0;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>
    Track Order #<?= $orderId ?>
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            180deg,
            #eef6f8,
            #f8fafc
        );

    color: #172033;
}

.container {
    width: min(950px, 94%);
    margin: 35px auto 60px;
}

.header {
    padding: 30px;

    border-radius: 24px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #155e75,
            #0f766e
        );

    box-shadow:
        0 15px 40px
        rgba(15, 118, 110, .18);

    margin-bottom: 20px;
}

.header h1 {
    margin: 0 0 8px;
}

.header p {
    margin: 5px 0;
    opacity: .9;
}

.card {
    background: white;

    border-radius: 22px;

    padding: 28px;

    margin-bottom: 20px;

    box-shadow:
        0 12px 40px
        rgba(15, 23, 42, .07);
}

.info-grid {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;
}

.info {
    padding: 16px;

    background: #f8fafc;

    border-radius: 14px;
}

.info small {
    color: #64748b;
    display: block;
    margin-bottom: 5px;
}

.info strong {
    display: block;
}

/*
|--------------------------------------------------------------------------
| Main progress
|--------------------------------------------------------------------------
*/

.progress {
    display: flex;

    align-items: flex-start;

    justify-content:
        space-between;

    position: relative;

    margin-top: 40px;
}

.progress::before {
    content: "";

    position: absolute;

    top: 17px;

    left: 6%;

    right: 6%;

    height: 4px;

    background: #dbe5ea;
}

.progress-step {
    width: 16.66%;

    position: relative;

    text-align: center;

    z-index: 2;
}

.circle {
    width: 36px;
    height: 36px;

    margin: 0 auto 10px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 800;

    background: #dbe5ea;

    color: #64748b;

    border: 4px solid white;
}

.progress-step.completed .circle,
.progress-step.active .circle {
    background: #0f766e;
    color: white;
}

.progress-step.active .circle {
    box-shadow:
        0 0 0 5px
        rgba(15, 118, 110, .15);
}

.step-label {
    font-size: 13px;

    font-weight: 700;

    color: #64748b;
}

.progress-step.completed .step-label,
.progress-step.active .step-label {
    color: #0f766e;
}

/*
|--------------------------------------------------------------------------
| History
|--------------------------------------------------------------------------
*/

.timeline {
    margin-top: 25px;
}

.event {
    position: relative;

    padding:
        0 0 28px 45px;
}

.event::before {
    content: "";

    position: absolute;

    left: 10px;
    top: 5px;

    width: 15px;
    height: 15px;

    border-radius: 50%;

    background: #0f766e;
}

.event:not(:last-child)::after {
    content: "";

    position: absolute;

    left: 16px;
    top: 20px;

    width: 3px;
    height: calc(100% - 8px);

    background: #d7e1e5;
}

.event-title {
    font-weight: 800;
    font-size: 16px;
}

.event-date {
    color: #64748b;

    font-size: 13px;

    margin-top: 5px;
}

.event-note {
    color: #475569;

    margin-top: 7px;
}

.back {
    display: inline-block;

    color: #0f766e;

    font-weight: 700;

    text-decoration: none;
}

@media (max-width: 700px) {

    .info-grid {
        grid-template-columns: 1fr;
    }

    .progress {
        display: block;
    }

    .progress::before {
        left: 18px;
        right: auto;
        top: 18px;
        bottom: 18px;
        width: 4px;
        height: auto;
    }

    .progress-step {
        width: 100%;
        min-height: 70px;
        display: flex;
        align-items: center;
        text-align: left;
    }

    .circle {
        margin: 0 15px 0 0;
        flex-shrink: 0;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <h1>
            Track Order #<?= $orderId ?>
        </h1>

        <p>
            <?= e((string) $order['customer_name']) ?>
        </p>

        <?php if (
            !empty($order['estimated_delivery'])
        ): ?>

            <p>
                Expected delivery:
                <strong>
                    <?= e(
                        date(
                            'd M Y',
                            strtotime(
                                (string)
                                $order['estimated_delivery']
                            )
                        )
                    ) ?>
                </strong>
            </p>

        <?php endif; ?>

    </div>


    <div class="card">

        <h2>
            Delivery Progress
        </h2>

        <div class="progress">

            <?php
            $stepNumber = 0;

            foreach ($steps as $status => $label):

                $completed =
                    $stepNumber < $currentIndex;

                $active =
                    $stepNumber === $currentIndex;

            ?>

                <div class="
                    progress-step
                    <?= $completed
                        ? 'completed'
                        : ''
                    ?>
                    <?= $active
                        ? 'active'
                        : ''
                    ?>
                ">

                    <div class="circle">

                        <?php if (
                            $completed ||
                            $active
                        ): ?>

                            ✓

                        <?php else: ?>

                            <?= $stepNumber + 1 ?>

                        <?php endif; ?>

                    </div>

                    <div class="step-label">
                        <?= e($label) ?>
                    </div>

                </div>

            <?php

                $stepNumber++;

            endforeach;

            ?>

        </div>

    </div>


    <div class="card">

        <h2>
            Delivery Information
        </h2>

        <div class="info-grid">

            <div class="info">

                <small>
                    Current Status
                </small>

                <strong>
                    <?= e(
                        delivery_status_label(
                            $currentStatus
                        )
                    ) ?>
                </strong>

            </div>


            <div class="info">

                <small>
                    Courier
                </small>

                <strong>
                    <?= e(
                        (string)
                        (
                            $order['courier_name']
                            ?: 'Not assigned'
                        )
                    ) ?>
                </strong>

            </div>


            <div class="info">

                <small>
                    Tracking Number
                </small>

                <strong>
                    <?= e(
                        (string)
                        (
                            $order['tracking_number']
                            ?: 'Not assigned'
                        )
                    ) ?>
                </strong>

            </div>

        </div>

    </div>


    <div class="card">

        <h2>
            Tracking History
        </h2>

        <div class="timeline">

            <?php if (!$history): ?>

                <p>
                    Tracking information is not available yet.
                </p>

            <?php else: ?>

                <?php foreach ($history as $event): ?>

                    <div class="event">

                        <div class="event-title">

                            <?= e(
                                delivery_status_label(
                                    (string)
                                    $event['status']
                                )
                            ) ?>

                        </div>

                        <div class="event-date">

                            <?= e(
                                date(
                                    'd M Y, h:i A',
                                    strtotime(
                                        (string)
                                        $event['created_at']
                                    )
                                )
                            ) ?>

                        </div>

                        <?php if (
                            !empty($event['note'])
                        ): ?>

                            <div class="event-note">

                                <?= e(
                                    (string)
                                    $event['note']
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>


    <a
        href="my_orders.php"
        class="back">

        ← Back to My Orders

    </a>

</div>

</body>

</html>