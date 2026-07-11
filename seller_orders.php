<?php
// seller_orders.php — orders containing the logged-in seller's products
declare(strict_types=1);

session_start();

if (empty($_SESSION['seller_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/db.php';

$sellerId = (int) $_SESSION['seller_id'];

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['seller_csrf'])) {
    $_SESSION['seller_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['seller_csrf'];

$successMessage = '';
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| UPDATE ORDER STATUS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $postedCsrf = (string) ($_POST['csrf'] ?? '');

    if (!hash_equals($_SESSION['seller_csrf'], $postedCsrf)) {

        $errorMessage = 'Invalid request. Please refresh and try again.';
    } else {

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $newStatus = strtolower(trim((string) ($_POST['status'] ?? '')));

        $allowedStatuses = [
            'pending',
            'processing',
            'paid',
            'shipped',
            'delivered',
            'cancelled'
        ];

        if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {

            $errorMessage = 'Invalid order or status.';
        } else {

            /*
            |--------------------------------------------------------------------------
            | MAKE SURE THIS ORDER CONTAINS THIS SELLER'S PRODUCT
            |--------------------------------------------------------------------------
            */

            $checkStmt = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM order_items oi
                INNER JOIN products p
                    ON p.id = oi.product_id
                WHERE oi.order_id = ?
                  AND p.seller_id = ?
            ");

            $checkStmt->bind_param('ii', $orderId, $sellerId);
            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();
            $checkRow = $checkResult->fetch_assoc();

            $sellerOwnsOrder = (int) ($checkRow['total'] ?? 0) > 0;

            $checkStmt->close();

            if (!$sellerOwnsOrder) {

                $errorMessage = 'You cannot update this order.';
            } else {

                /*
                |--------------------------------------------------------------------------
                | UPDATE STATUS IN ORDERS TABLE
                |--------------------------------------------------------------------------
                */

                $updateStmt = $conn->prepare("
                    UPDATE orders
                    SET status = ?
                    WHERE id = ?
                ");

                $updateStmt->bind_param(
                    'si',
                    $newStatus,
                    $orderId
                );

                if ($updateStmt->execute()) {

                    $successMessage =
                        'Order #' .
                        $orderId .
                        ' status updated to ' .
                        ucfirst($newStatus) .
                        '.';
                } else {

                    $errorMessage = 'Failed to update order status.';
                }

                $updateStmt->close();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET ONLY ORDERS FOR THIS SELLER'S PRODUCTS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        o.id AS order_id,
        o.user_email,
        o.payment_method,
        o.status,
        o.created_at,

        p.id AS product_id,
        p.name AS product_name,

        oi.quantity,
        oi.price,

        (oi.quantity * oi.price) AS item_total

    FROM order_items oi

    INNER JOIN orders o
        ON o.id = oi.order_id

    INNER JOIN products p
        ON p.id = oi.product_id

    WHERE p.seller_id = ?

    ORDER BY o.created_at DESC, o.id DESC
");

$stmt->bind_param('i', $sellerId);
$stmt->execute();

$result = $stmt->get_result();

$orders = $result
    ? $result->fetch_all(MYSQLI_ASSOC)
    : [];

$stmt->close();

/*
|--------------------------------------------------------------------------
| BUILD PAGE CONTENT
|--------------------------------------------------------------------------
*/

ob_start();
?>

<style>
    .table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .table {
        width: 100%;
        table-layout: auto;
    }

    .table th,
    .table td {
        padding: 14px 10px;
        vertical-align: middle;
        white-space: nowrap;
    }

    /* Give the action column enough room */
    .table th:last-child,
    .table td:last-child {
        min-width: 310px;
    }

    .status-actions {
        display: grid;
        grid-template-columns: repeat(3, max-content);
        gap: 7px;
        align-items: center;
    }

    .status-form {
        margin: 0;
        display: inline-block;
    }

    .status-btn {
        border: 1px solid var(--border);
        background: transparent;
        color: var(--text);
        padding: 7px 11px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        white-space: nowrap;
        transition: transform 0.15s ease, opacity 0.15s ease;
    }

    .status-btn:hover {
        transform: translateY(-1px);
        opacity: 0.85;
    }

    .status-btn.processing {
        background: #2563eb;
        border-color: #2563eb;
        color: white;
    }

    .status-btn.paid {
        background: #6d28d9;
        border-color: #6d28d9;
        color: white;
    }

    .status-btn.shipped {
        background: #334155;
        border-color: #475569;
        color: white;
    }

    .status-btn.delivered {
        background: #10b981;
        border-color: #10b981;
        color: white;
    }

    .status-btn.cancelled {
        background: #f59e0b;
        border-color: #f59e0b;
        color: #111827;
    }

    .status-btn.current {
        box-shadow:
            0 0 0 2px var(--panel),
            0 0 0 4px #38bdf8;
    }

    .message {
        padding: 12px 15px;
        margin-bottom: 18px;
        border-radius: 10px;
        font-weight: 700;
    }

    .message-success {
        background: rgba(16, 185, 129, 0.15);
        border: 1px solid rgba(16, 185, 129, 0.35);
        color: #34d399;
    }

    .message-error {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.35);
        color: #f87171;
    }
</style>
<div class="grid">

    <div class="card col-12">

        <h3>Orders for My Products</h3>

        <?php if ($successMessage !== ''): ?>

            <div class="message message-success">
                <?= e($successMessage) ?>
            </div>

        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>

            <div class="message message-error">
                <?= e($errorMessage) ?>
            </div>

        <?php endif; ?>

        <div class="table-wrap">

            <table class="table">

                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Item Total</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($orders)): ?>

                        <tr>
                            <td
                                colspan="10"
                                style="
                                    text-align:center;
                                    color:var(--muted);
                                    padding:30px;
                                ">
                                No orders found for your products yet.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($orders as $order): ?>

                            <?php
                            $status = strtolower(
                                (string) $order['status']
                            );

                            $badgeClass = 'badge';

                            if ($status === 'delivered') {

                                $badgeClass = 'badge badge--ok';
                            } elseif (
                                $status === 'pending' ||
                                $status === 'cancelled'
                            ) {

                                $badgeClass = 'badge badge--warn';
                            }

                            $statuses = [
                                'pending'    => 'Pending',
                                'processing' => 'Processing',
                                'paid'       => 'Paid',
                                'shipped'    => 'Shipped',
                                'delivered'  => 'Delivered',
                                'cancelled'  => 'Cancelled'
                            ];
                            ?>

                            <tr>

                                <td style="font-weight:800;">
                                    #<?= (int) $order['order_id'] ?>
                                </td>

                                <td>
                                    <?= e((string) $order['user_email']) ?>
                                </td>

                                <td style="font-weight:700;">
                                    <?= e((string) $order['product_name']) ?>
                                </td>

                                <td>
                                    <?= (int) $order['quantity'] ?>
                                </td>

                                <td>
                                    Tk <?= number_format(
                                            (float) $order['price'],
                                            2
                                        ) ?>
                                </td>

                                <td style="font-weight:800;">
                                    Tk <?= number_format(
                                            (float) $order['item_total'],
                                            2
                                        ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        strtoupper(
                                            (string) $order['payment_method']
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        date(
                                            'Y-m-d H:i',
                                            strtotime(
                                                (string) $order['created_at']
                                            )
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <span class="<?= $badgeClass ?>">
                                        <?= e(
                                            ucfirst(
                                                (string) $order['status']
                                            )
                                        ) ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="status-actions">

                                        <?php foreach ($statuses as $statusValue => $statusLabel): ?>

                                            <form
                                                method="POST"
                                                class="status-form">

                                                <input
                                                    type="hidden"
                                                    name="csrf"
                                                    value="<?= e($csrf) ?>">

                                                <input
                                                    type="hidden"
                                                    name="order_id"
                                                    value="<?= (int) $order['order_id'] ?>">

                                                <input
                                                    type="hidden"
                                                    name="status"
                                                    value="<?= e($statusValue) ?>">

                                                <button
                                                    type="submit"
                                                    name="update_status"
                                                    class="
                                                        status-btn
                                                        <?= e($statusValue) ?>
                                                        <?= $status === $statusValue
                                                            ? 'current'
                                                            : ''
                                                        ?>
                                                    ">
                                                    <?= e($statusLabel) ?>
                                                </button>

                                            </form>

                                        <?php endforeach; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php

$content = ob_get_clean();

$pageTitle = 'My Orders';

$actions = [];

include __DIR__ . '/seller_layout.php';
