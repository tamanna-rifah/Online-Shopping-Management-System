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
    $successMessage =
    'Order #' .
    $orderId .
    ' status updated to ' .
    ucfirst($newStatus) .
    '.';

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
if (
    isset($_GET['updated']) &&
    isset($_GET['delivery'])
) {
    $successMessage =
        'Order #' .
        (int) $_GET['updated'] .
        ' delivery status updated to ' .
        htmlspecialchars(
            (string) $_GET['delivery'],
            ENT_QUOTES,
            'UTF-8'
        ) .
        '.';
}

/*
|--------------------------------------------------------------------------
| GET ONLY ORDERS FOR THIS SELLER'S PRODUCTS
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| UPDATE PAYMENT STATUS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_payment_status'])
) {

    $postedCsrf = (string) ($_POST['csrf'] ?? '');

    if (!hash_equals($_SESSION['seller_csrf'], $postedCsrf)) {

        $errorMessage =
            'Invalid request. Please refresh and try again.';

    } else {

        $orderId = (int) ($_POST['order_id'] ?? 0);

        $newPaymentStatus = strtolower(
            trim(
                (string) (
                    $_POST['payment_status'] ?? ''
                )
            )
        );

        if (
            $orderId <= 0 ||
            !in_array(
                $newPaymentStatus,
                ['pending', 'success'],
                true
            )
        ) {

            $errorMessage =
                'Invalid payment status.';

        } else {

            $updatePayment = $conn->prepare("
                UPDATE orders
                SET payment_status = ?
                WHERE id = ?
            ");

            $updatePayment->bind_param(
                'si',
                $newPaymentStatus,
                $orderId
            );

            if ($updatePayment->execute()) {

                $successMessage =
                    'Order #' .
                    $orderId .
                    ' payment updated to ' .
                    (
                        $newPaymentStatus === 'success'
                            ? 'Paid'
                            : 'Unpaid'
                    ) .
                    '.';

            } else {

                $errorMessage =
                    'Failed to update payment status.';
            }

            $updatePayment->close();
        }
    }
}
$stmt = $conn->prepare("
    SELECT
    o.id AS order_id,
    o.user_email,
    o.payment_method,
    o.payment_status,
    o.status,
    o.delivery_status,
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
| UPDATE DELIVERY STATUS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_delivery_status'])
) {

    $postedCsrf = (string) ($_POST['csrf'] ?? '');

    if (!hash_equals($_SESSION['seller_csrf'], $postedCsrf)) {

        $errorMessage =
            'Invalid request. Please refresh and try again.';

    } else {

        $orderId = (int) ($_POST['order_id'] ?? 0);

        $newDeliveryStatus = strtolower(
            trim(
                (string) (
                    $_POST['delivery_status'] ?? ''
                )
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Delivery status labels
        |--------------------------------------------------------------------------
        */

        $deliveryLabels = [
            'order_placed'     => 'Order Placed',
            'processing'       => 'Processing',
            'packed'           => 'Packed',
            'shipped'          => 'Shipped',
            'out_for_delivery' => 'Out for Delivery',
            'delivered'        => 'Delivered'
        ];

        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        if (
            $orderId <= 0 ||
            !array_key_exists(
                $newDeliveryStatus,
                $deliveryLabels
            )
        ) {

            $errorMessage =
                'Invalid delivery status.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Check seller owns this order
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

            $checkStmt->bind_param(
                'ii',
                $orderId,
                $sellerId
            );

            $checkStmt->execute();

            $checkRow =
                $checkStmt
                    ->get_result()
                    ->fetch_assoc();

            $checkStmt->close();

            if ((int) ($checkRow['total'] ?? 0) <= 0) {

                $errorMessage =
                    'You cannot update this delivery.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Get current delivery status
                |--------------------------------------------------------------------------
                */

                $oldStmt = $conn->prepare("
                    SELECT delivery_status
                    FROM orders
                    WHERE id = ?
                    LIMIT 1
                ");

                $oldStmt->bind_param(
                    'i',
                    $orderId
                );

                $oldStmt->execute();

                $oldRow =
                    $oldStmt
                        ->get_result()
                        ->fetch_assoc();

                $oldStmt->close();

                $oldDeliveryStatus =
                    (string) (
                        $oldRow['delivery_status']
                        ?? 'order_placed'
                    );

                /*
                |--------------------------------------------------------------------------
                | UPDATE DELIVERY STATUS
                |--------------------------------------------------------------------------
                */

                $updateDelivery = $conn->prepare("
                    UPDATE orders
                    SET delivery_status = ?
                    WHERE id = ?
                ");

                $updateDelivery->bind_param(
                    'si',
                    $newDeliveryStatus,
                    $orderId
                );

                if ($updateDelivery->execute()) {

                    /*
                    |--------------------------------------------------------------------------
                    | Save tracking history
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $oldDeliveryStatus
                        !== $newDeliveryStatus
                    ) {

                        $note =
                            $deliveryLabels[
                                $newDeliveryStatus
                            ];

                        $trackingStmt = $conn->prepare("
                            INSERT INTO order_tracking
                            (
                                order_id,
                                status,
                                note
                            )
                            VALUES (?, ?, ?)
                        ");

                        $trackingStmt->bind_param(
                            'iss',
                            $orderId,
                            $newDeliveryStatus,
                            $note
                        );

                        $trackingStmt->execute();
                        $trackingStmt->close();
                    }

                    $updateDelivery->close();

                    /*
                    |--------------------------------------------------------------------------
                    | REDIRECT AFTER UPDATE
                    |--------------------------------------------------------------------------
                    */

                    header(
                        'Location: seller_orders.php?updated=' .
                        $orderId .
                        '&delivery=' .
                        urlencode(
                            $deliveryLabels[
                                $newDeliveryStatus
                            ]
                        )
                    );

                    exit();

                } else {

                    $errorMessage =
                        'Failed to update delivery status.';

                    $updateDelivery->close();
                }
            }
        }
    }
}

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
    min-width: 380px;
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
    .payment-actions {
    display: flex;
    gap: 7px;
    align-items: center;
}

.payment-btn {
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text);
    padding: 7px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

.payment-btn.paid {
    background: #6d28d9;
    border-color: #6d28d9;
    color: white;
}

.payment-btn.unpaid {
    background: #64748b;
    border-color: #64748b;
    color: white;
}

.payment-btn.current {
    box-shadow:
        0 0 0 2px var(--panel),
        0 0 0 4px #38bdf8;
}

.delivery-actions {
    display: grid;
    grid-template-columns: repeat(2, max-content);
    gap: 7px;
    align-items: center;
}

.delivery-form {
    margin: 0;
}

.delivery-btn {
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text);
    padding: 7px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    white-space: nowrap;
}

.delivery-btn.current {
    background: #0f766e;
    border-color: #0f766e;
    color: white;

    box-shadow:
        0 0 0 2px var(--panel),
        0 0 0 4px #38bdf8;
}

.payment-btn:hover,
.delivery-btn:hover {
    opacity: 0.85;
    transform: translateY(-1px);
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
                       <th>Delivery Status</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($orders)): ?>

                        <tr>
                            <td
                                colspan="11"
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

$displayStatus =
    $status === 'delivered'
        ? 'Delivered'
        : 'Pending';

$badgeClass =
    $status === 'delivered'
        ? 'badge badge--ok'
        : 'badge badge--warn';

                            $deliveryStatuses = [
    'order_placed'     => 'Order Placed',
    'processing'       => 'Processing',
    'packed'           => 'Packed',
    'shipped'          => 'Shipped',
    'out_for_delivery' => 'Out for Delivery',
    'delivered'        => 'Delivered'
];

$deliveryStatus = strtolower(
    (string) ($order['delivery_status'] ?? 'order_placed')
);

$paymentStatus = strtolower(
    (string) ($order['payment_status'] ?? 'pending')
);
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
        <?= e($displayStatus) ?>
    </span>
</td>
<td>

    <div class="payment-actions">

        <form method="POST">

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
                name="payment_status"
                value="success">

            <button
                type="submit"
                name="update_payment_status"
                class="payment-btn paid
                <?= $paymentStatus === 'success'
                    ? 'current'
                    : ''
                ?>">

                Paid

            </button>

        </form>

        <form method="POST">

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
                name="payment_status"
                value="pending">

            <button
                type="submit"
                name="update_payment_status"
                class="payment-btn unpaid
                <?= $paymentStatus !== 'success'
                    ? 'current'
                    : ''
                ?>">

                Unpaid

            </button>

        </form>

    </div>

</td>
<td>

    <div class="delivery-actions">

        <?php foreach (
            $deliveryStatuses
            as $deliveryValue => $deliveryLabel
        ): ?>

            <form
                method="POST"
                class="delivery-form">

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
                    name="delivery_status"
                    value="<?= e($deliveryValue) ?>">

                <button
                    type="submit"
                    name="update_delivery_status"
                    class="
                        delivery-btn
                        <?= $deliveryStatus === $deliveryValue
                            ? 'current'
                            : ''
                        ?>
                    ">

                    <?= e($deliveryLabel) ?>

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
