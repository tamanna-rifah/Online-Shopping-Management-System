<?php
require_once __DIR__ . '/auth.php';
start_auth_session();
require 'db.php';

if (!isset($_SESSION['user_email'])) {
    header('Location: user_login.php');
    exit();
}

$user_email = $_SESSION['user_email'];
$flash_message = '';
$flash_type = 'success';

if (isset($_GET['paid'])) {
    $flash_message = 'Order #' . (int) $_GET['paid'] . ' was paid successfully with Online Payment.';
} elseif (isset($_GET['order_placed'])) {
    $flash_message = 'Order #' . (int) $_GET['order_placed'] . ' was placed successfully.';
} elseif (isset($_GET['payment_failed'])) {
    $flash_message = 'Payment failed for order #' . (int) $_GET['payment_failed'] . '.';
    $flash_type = 'danger';
} elseif (isset($_GET['payment_cancelled'])) {
    $flash_message = 'Payment was cancelled for order #' . (int) $_GET['payment_cancelled'] . '.';
    $flash_type = 'warning';
}

$query = $conn->prepare("
    SELECT
        o.id AS order_id,
        o.status,
        o.payment_method,
        o.grand_total,
        o.created_at,
        COALESCE(pay.status, 'pending') AS payment_status,
        COALESCE(pay.transaction_id, '') AS transaction_id,
        COALESCE(pay.payment_id, '') AS payment_id,
        COALESCE(pay.trx_id, '') AS trx_id,
        COALESCE(pay.customer_name, '') AS customer_name,
        COALESCE(pay.customer_phone, '') AS customer_phone,
        GROUP_CONCAT(CONCAT(p.id, ':', p.name, ' (x', oi.quantity, ')') SEPARATOR ',') AS items
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN payments pay ON pay.order_id = o.id
    WHERE o.user_email = ?
    GROUP BY
        o.id, o.status, o.payment_method, o.grand_total, o.created_at,
        pay.status, pay.transaction_id, pay.payment_id, pay.trx_id, pay.customer_name, pay.customer_phone
    ORDER BY o.created_at DESC
");
$query->bind_param('s', $user_email);
$query->execute();
$ordersResult = $query->get_result();
$orders = $ordersResult ? $ordersResult->fetch_all(MYSQLI_ASSOC) : [];

$stats = [
    'total' => count($orders),
    'pending' => 0,
    'success' => 0,
    'delivered' => 0,
];

foreach ($orders as $order) {
    $status = strtolower((string) $order['status']);
    $paymentStatus = strtolower((string) $order['payment_status']);
    if ($paymentStatus === 'pending') {
        $stats['pending']++;
    }
    if ($paymentStatus === 'success') {
        $stats['success']++;
    }
    if ($status === 'delivered') {
        $stats['delivered']++;
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function order_status_class(string $status): string
{
    return match (strtolower($status)) {
        'pending' => 'gold',
        'processing' => 'slate',
        'paid' => 'sky',
        'shipped' => 'indigo',
        'delivered' => 'green',
        'cancelled' => 'red',
        default => 'slate',
    };
}

function payment_status_class(string $status): string
{
    return match (strtolower($status)) {
        'success' => 'green',
        'failed' => 'red',
        'pending' => 'gold',
        default => 'slate',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f7fb;
            --panel: rgba(255, 255, 255, 0.96);
            --text: #132238;
            --muted: #64748b;
            --line: #dbe5f0;
            --brand: #0f766e;
            --brand-strong: #155e75;
            --accent: #f59e0b;
            --danger: #dc2626;
            --success: #15803d;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 30%),
                radial-gradient(circle at top right, rgba(14, 165, 233, 0.12), transparent 26%),
                linear-gradient(180deg, #eef4f8 0%, #f7f9fc 100%);
        }

        .shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 32px 18px 56px;
        }

        .hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 22px;
            padding: 28px;
            border-radius: 28px;
            color: #fff;
            background:
                linear-gradient(135deg, rgba(21, 94, 117, 0.96), rgba(15, 118, 110, 0.92)),
                linear-gradient(135deg, #155e75, #0f766e);
            box-shadow: var(--shadow);
        }

        .hero h1 {
            margin: 0 0 10px;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.05;
        }

        .hero p {
            margin: 0;
            max-width: 620px;
            color: rgba(255, 255, 255, 0.82);
            line-height: 1.7;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 999px;
            font-weight: 700;
            text-decoration: none;
            transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            color: #0f172a;
            background: #fff3cf;
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.14);
        }

        .btn-secondary {
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.28);
            background: rgba(255, 255, 255, 0.12);
        }

        .flash {
            margin-bottom: 18px;
            padding: 16px 18px;
            border-radius: 18px;
            font-weight: 600;
            border: 1px solid transparent;
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.06);
        }

        .flash.success {
            color: #166534;
            background: #e8fff0;
            border-color: #bbf7d0;
        }

        .flash.warning {
            color: #92400e;
            background: #fff8df;
            border-color: #fde68a;
        }

        .flash.danger {
            color: #991b1b;
            background: #fef0f0;
            border-color: #fecaca;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .stat-card {
            padding: 20px;
            border-radius: 22px;
            background: var(--panel);
            border: 1px solid rgba(219, 229, 240, 0.8);
            box-shadow: var(--shadow);
        }

        .stat-label {
            color: var(--muted);
            font-size: 0.92rem;
            font-weight: 600;
        }

        .stat-value {
            margin-top: 10px;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .orders-grid {
            display: grid;
            gap: 20px;
        }

        .order-card {
            padding: 24px;
            border-radius: 26px;
            background: var(--panel);
            border: 1px solid rgba(219, 229, 240, 0.85);
            box-shadow: var(--shadow);
        }

        .order-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 18px;
        }

        .order-title {
            margin: 0;
            font-size: 1.45rem;
            font-weight: 800;
        }

        .order-meta {
            margin-top: 8px;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .badge.gold { background: #fff6db; color: #a16207; }
        .badge.slate { background: #eef2f7; color: #475569; }
        .badge.sky { background: #e0f2fe; color: #0369a1; }
        .badge.indigo { background: #e8eaff; color: #4338ca; }
        .badge.green { background: #eafaf0; color: #15803d; }
        .badge.red { background: #fdeaea; color: #b91c1c; }

        .order-layout {
            display: grid;
            grid-template-columns: 1.35fr 0.95fr;
            gap: 18px;
        }

        .panel {
            padding: 18px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.78));
            border: 1px solid rgba(219, 229, 240, 0.95);
        }

        .panel h3 {
            margin: 0 0 14px;
            font-size: 1rem;
            font-weight: 800;
        }

        .items-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 10px;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid rgba(226, 232, 240, 0.92);
        }

        .item-name {
            font-weight: 700;
            line-height: 1.45;
        }

        .review-btn,
        .already {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .review-btn {
            color: #fff;
            background: linear-gradient(135deg, #16a34a, #15803d);
        }

        .already {
            color: #166534;
            background: #ecfdf3;
            border: 1px solid #bbf7d0;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .detail {
            padding: 14px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid rgba(226, 232, 240, 0.92);
        }

        .detail.full {
            grid-column: 1 / -1;
        }

        .detail-label {
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .detail-value {
            margin-top: 6px;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.5;
            word-break: break-word;
        }

        .price {
            font-size: 1.35rem;
            color: var(--brand-strong);
        }

        .empty-state {
            padding: 52px 26px;
            border-radius: 28px;
            text-align: center;
            background: var(--panel);
            border: 1px solid rgba(219, 229, 240, 0.8);
            box-shadow: var(--shadow);
        }

        .empty-state h2 {
            margin: 0 0 10px;
            font-size: 1.8rem;
        }

        .empty-state p {
            margin: 0 auto 20px;
            max-width: 540px;
            color: var(--muted);
            line-height: 1.8;
        }

        @media (max-width: 980px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .order-layout {
                grid-template-columns: 1fr;
            }

            .order-top,
            .badge-row {
                align-items: flex-start;
            }
        }

        @media (max-width: 640px) {
            .shell {
                padding: 18px 14px 36px;
            }

            .hero {
                padding: 22px 18px;
                border-radius: 24px;
                flex-direction: column;
            }

            .hero-actions,
            .badge-row {
                justify-content: flex-start;
            }

            .stats,
            .details-grid {
                grid-template-columns: 1fr;
            }

            .order-card {
                padding: 18px;
                border-radius: 22px;
            }

            .item-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="hero">
            <div>
                <h1>My Orders</h1>
                <p>Track every purchase in one place, review delivered products, and keep an eye on your payment progress without digging through old pages.</p>
            </div>
            <div class="hero-actions">
                <a href="user_dashboard.php" class="btn btn-secondary">Back to Shop</a>
                <a href="cart.php" class="btn btn-primary">Open Cart</a>
            </div>
        </section>

        <?php if ($flash_message !== ''): ?>
            <div class="flash <?= h($flash_type) ?>"><?= h($flash_message) ?></div>
        <?php endif; ?>

        <section class="stats">
            <div class="stat-card">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value"><?= (int) $stats['total'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending Payments</div>
                <div class="stat-value"><?= (int) $stats['pending'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Successful Payments</div>
                <div class="stat-value"><?= (int) $stats['success'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Delivered Orders</div>
                <div class="stat-value"><?= (int) $stats['delivered'] ?></div>
            </div>
        </section>

        <section class="orders-grid">
            <?php if ($orders): ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $status = strtolower((string) $order['status']);
                    $paymentStatus = strtolower((string) $order['payment_status']);
                    $items = explode(',', (string) $order['items']);
                    ?>
                    <article class="order-card">
                        <div class="order-top">
                            <div>
                                <h2 class="order-title">Order #<?= (int) $order['order_id'] ?></h2>
                                <div class="order-meta">Placed on <?= h(date('F j, Y \a\t g:i A', strtotime((string) $order['created_at']))) ?></div>
                            </div>
                            <div class="badge-row">
                                <span class="badge <?= h(order_status_class($status)) ?>"><?= h(ucfirst($status)) ?></span>
                                <span class="badge <?= h(payment_status_class($paymentStatus)) ?>">Payment <?= h(ucfirst($paymentStatus)) ?></span>
                            </div>
                        </div>

                        <div class="order-layout">
                            <div class="panel">
                                <h3>Items in this order</h3>
                                <ul class="items-list">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        [$pid, $pname] = explode(':', $item, 2);
                                        $reviewAction = '';

                                        if ($status === 'delivered') {
                                            $check = $conn->prepare("
                                                SELECT id
                                                FROM reviews
                                                WHERE product_id = ? AND order_id = ? AND user_email = ?
                                                LIMIT 1
                                            ");
                                            $check->bind_param('iis', $pid, $order['order_id'], $user_email);
                                            $check->execute();
                                            $review_result = $check->get_result();

                                            if ($review_result->num_rows > 0) {
                                                $reviewAction = '<span class="already">Reviewed</span>';
                                            } else {
                                                $reviewAction = '<a href="add_review.php?product_id=' . (int) $pid . '&order_id=' . (int) $order['order_id'] . '" class="review-btn">Add Review</a>';
                                            }
                                            $check->close();
                                        }
                                        ?>
                                        <li class="item-row">
                                            <div class="item-name"><?= h($pname) ?></div>
                                            <?= $reviewAction ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>

                            <div class="panel">
                                <h3>Order details</h3>
                                <div class="details-grid">
                                    <div class="detail">
                                        <div class="detail-label">Total</div>
                                        <div class="detail-value price"><?= h(number_format((float) $order['grand_total'], 2)) ?> BDT</div>
                                    </div>
                                    <div class="detail">
                                        <div class="detail-label">Payment Method</div>
                                        <div class="detail-value"><?= h((string) $order['payment_method'] === 'online' ? 'Online Payment' : 'Cash on Delivery') ?></div>
                                    </div>
                                    <div class="detail">
                                        <div class="detail-label">Payment Status</div>
                                        <div class="detail-value"><?= h(ucfirst($paymentStatus)) ?></div>
                                    </div>
                                    <div class="detail">
                                        <div class="detail-label">Order Status</div>
                                        <div class="detail-value"><?= h(ucfirst($status)) ?></div>
                                    </div>

                                    <?php if (!empty($order['payment_id'])): ?>
                                        <div class="detail full">
                                            <div class="detail-label">Gateway Payment ID</div>
                                            <div class="detail-value"><?= h((string) $order['payment_id']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($order['trx_id'])): ?>
                                        <div class="detail full">
                                            <div class="detail-label">Bank Transaction ID</div>
                                            <div class="detail-value"><?= h((string) $order['trx_id']) ?></div>
                                        </div>
                                    <?php elseif (!empty($order['transaction_id'])): ?>
                                        <div class="detail full">
                                            <div class="detail-label">Transaction Reference</div>
                                            <div class="detail-value"><?= h((string) $order['transaction_id']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($order['customer_name']) || !empty($order['customer_phone'])): ?>
                                        <div class="detail full">
                                            <div class="detail-label">Customer Info</div>
                                            <div class="detail-value">
                                                <?= h(trim((string) $order['customer_name'])) ?>
                                                <?php if (!empty($order['customer_phone'])): ?>
                                                    <br><?= h((string) $order['customer_phone']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="detail full">
    <a
        href="track_order.php?id=<?= (int) $order['order_id'] ?>"
        class="btn btn-primary"
        style="display:inline-block; margin-top:10px;">

        Track Order

    </a>
                            </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h2>No orders yet</h2>
                    <p>You have not placed any orders yet. Browse the shop, add something you like to your cart, and your orders will appear here in a much nicer timeline.</p>
                    <a href="user_dashboard.php" class="btn btn-primary">Start Shopping</a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
