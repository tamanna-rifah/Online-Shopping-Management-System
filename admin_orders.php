<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/db.php';

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf'];

$allowedStatuses = [
    'pending',
    'processing',
    'paid',
    'shipped',
    'delivered',
    'cancelled',
];

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $postedCsrf = (string) ($_POST['csrf'] ?? '');

    if (!hash_equals($csrf, $postedCsrf)) {
        $errorMessage = 'Invalid request. Please refresh and try again.';
    } else {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $newStatus = strtolower(trim((string) ($_POST['status'] ?? '')));

        if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            $errorMessage = 'Invalid order or status.';
        } else {
            $stmt = $conn->prepare("
                UPDATE orders
                SET status = ?
                WHERE id = ?
            ");
            $stmt->bind_param('si', $newStatus, $orderId);

            if ($stmt->execute() && $stmt->affected_rows >= 0) {
                $successMessage = 'Order #' . $orderId . ' updated to ' . ucfirst($newStatus) . '.';
            } else {
                $errorMessage = 'Failed to update order status.';
            }

            $stmt->close();
        }
    }
}

$qStatus = trim((string) ($_GET['status'] ?? ''));
$qEmail = trim((string) ($_GET['email'] ?? ''));
$qFrom = trim((string) ($_GET['from'] ?? ''));
$qTo = trim((string) ($_GET['to'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($qStatus !== '' && in_array($qStatus, $allowedStatuses, true)) {
    $where[] = 'LOWER(o.status) = ?';
    $params[] = $qStatus;
    $types .= 's';
} else {
    $qStatus = '';
}

if ($qEmail !== '') {
    $where[] = 'o.user_email LIKE ?';
    $params[] = '%' . $qEmail . '%';
    $types .= 's';
}

if ($qFrom !== '') {
    $where[] = 'o.created_at >= ?';
    $params[] = $qFrom . ' 00:00:00';
    $types .= 's';
}

if ($qTo !== '') {
    $where[] = 'o.created_at <= ?';
    $params[] = $qTo . ' 23:59:59';
    $types .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'delivered_orders' => 0,
    'total_revenue' => 0.0,
];

$result = $conn->query("
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN LOWER(status) = 'delivered' THEN 1 ELSE 0 END) AS delivered_orders,
        COALESCE(SUM(grand_total), 0) AS total_revenue
    FROM orders
");

if ($result) {
    $stats = $result->fetch_assoc() ?: $stats;
    $result->close();
}

$sql = "
    SELECT
        o.id,
        o.user_email,
        COALESCE(u.name, '') AS customer_name,
        o.payment_method,
        o.status,
        o.created_at,
        o.grand_total,
        COUNT(oi.id) AS item_count,
        COALESCE(SUM(oi.quantity), 0) AS total_quantity
    FROM orders o
    LEFT JOIN users u
        ON u.email = o.user_email
    LEFT JOIN order_items oi
        ON oi.order_id = o.id
    $whereSql
    GROUP BY
        o.id,
        o.user_email,
        u.name,
        o.payment_method,
        o.status,
        o.created_at,
        o.grand_total
    ORDER BY o.created_at DESC, o.id DESC
";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

ob_start();
?>

<style>
    .admin-stat-number {
        font-size: 1.8rem;
        font-weight: 800;
        margin-top: 8px;
    }

    .admin-stat-note {
        color: var(--muted);
        font-size: .82rem;
        margin-top: 7px;
    }

    .orders-filter {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 10px;
        margin-bottom: 18px;
    }

    .filter-field {
        grid-column: span 3;
    }

    .filter-actions {
        grid-column: span 3;
        display: flex;
        align-items: flex-end;
        gap: 8px;
    }

    .filter-label {
        display: block;
        font-weight: 800;
        color: var(--muted);
        margin-bottom: 6px;
    }

    .filter-input {
        width: 100%;
        padding: 10px;
        border-radius: 10px;
        background: var(--panel);
        color: var(--text);
        border: 1px solid var(--border);
    }

    .table-wrap {
        overflow-x: auto;
    }

    .table th:last-child,
    .table td:last-child {
        min-width: 310px;
    }

    .status-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .status-form {
        margin: 0;
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
        background: #059669;
        border-color: #059669;
        color: white;
    }

    .status-btn.cancelled {
        background: #dc2626;
        border-color: #dc2626;
        color: white;
    }

    @media (max-width: 900px) {
        .filter-field,
        .filter-actions {
            grid-column: span 6;
        }
    }

    @media (max-width: 640px) {
        .filter-field,
        .filter-actions {
            grid-column: span 12;
        }
    }
</style>

<div class="grid">
    <div class="card col-3">
        <h3>Total Orders</h3>
        <div class="admin-stat-number"><?= (int) ($stats['total_orders'] ?? 0) ?></div>
        <div class="admin-stat-note">All customer orders</div>
    </div>

    <div class="card col-3">
        <h3>Pending Orders</h3>
        <div class="admin-stat-number"><?= (int) ($stats['pending_orders'] ?? 0) ?></div>
        <div class="admin-stat-note">Need admin follow-up</div>
    </div>

    <div class="card col-3">
        <h3>Delivered Orders</h3>
        <div class="admin-stat-number"><?= (int) ($stats['delivered_orders'] ?? 0) ?></div>
        <div class="admin-stat-note">Completed deliveries</div>
    </div>

    <div class="card col-3">
        <h3>Total Revenue</h3>
        <div class="admin-stat-number">Tk <?= number_format((float) ($stats['total_revenue'] ?? 0), 2) ?></div>
        <div class="admin-stat-note">Order grand total sum</div>
    </div>

    <div class="card col-12">
        <h3>Marketplace Orders</h3>

        <?php if ($successMessage !== ''): ?>
            <div class="card" style="background:rgba(16,185,129,.08); border-color:#10b981; color:#10b981; margin-bottom:12px;">
                <?= e($successMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="card" style="background:rgba(239,68,68,.08); border-color:#ef4444; color:#ef4444; margin-bottom:12px;">
                <?= e($errorMessage) ?>
            </div>
        <?php endif; ?>

        <form method="get" class="orders-filter">
            <div class="filter-field">
                <label class="filter-label" for="email">Customer Email</label>
                <input class="filter-input" id="email" type="text" name="email" value="<?= e($qEmail) ?>" placeholder="Search email">
            </div>

            <div class="filter-field">
                <label class="filter-label" for="status">Status</label>
                <select class="filter-input" id="status" name="status">
                    <option value="">All Status</option>
                    <?php foreach ($allowedStatuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= $qStatus === $status ? 'selected' : '' ?>>
                            <?= e(ucfirst($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label class="filter-label" for="from">From Date</label>
                <input class="filter-input" id="from" type="date" name="from" value="<?= e($qFrom) ?>">
            </div>

            <div class="filter-field">
                <label class="filter-label" for="to">To Date</label>
                <input class="filter-input" id="to" type="date" name="to" value="<?= e($qTo) ?>">
            </div>

            <div class="filter-actions">
                <button class="btn" type="submit">Filter</button>
                <a class="btn" href="admin_orders.php">Reset</a>
            </div>
        </form>

        <div class="table-wrap">
            <table class="table" aria-label="Admin orders table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Qty</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$orders): ?>
                        <tr>
                            <td colspan="9" style="text-align:center; color:var(--muted);">No orders found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $status = strtolower((string) $order['status']);
                            $badgeClass = 'badge';

                            if ($status === 'delivered') {
                                $badgeClass = 'badge badge--ok';
                            } elseif ($status === 'cancelled') {
                                $badgeClass = 'badge badge--danger';
                            } elseif (in_array($status, ['pending', 'processing', 'paid', 'shipped'], true)) {
                                $badgeClass = 'badge badge--warn';
                            }

                            $customerName = trim((string) $order['customer_name']);
                            $customer = $customerName !== '' ? $customerName : (string) $order['user_email'];
                            ?>
                            <tr>
                                <td>
                                    <a class="link" href="sales_details.php?order=<?= (int) $order['id'] ?>">
                                        #<?= (int) $order['id'] ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="font-weight:800;"><?= e($customer) ?></div>
                                    <div style="color:var(--muted); font-size:.85rem;"><?= e((string) $order['user_email']) ?></div>
                                </td>
                                <td><?= (int) $order['item_count'] ?></td>
                                <td><?= (int) $order['total_quantity'] ?></td>
                                <td><?= e(strtoupper((string) $order['payment_method'])) ?></td>
                                <td><span class="<?= $badgeClass ?>"><?= e(ucfirst($status)) ?></span></td>
                                <td><?= e(date('Y-m-d H:i', strtotime((string) $order['created_at']))) ?></td>
                                <td style="font-weight:800;">Tk <?= number_format((float) $order['grand_total'], 2) ?></td>
                                <td>
                                    <div class="status-actions">
                                        <a
    href="manage_delivery.php?id=<?= (int) $order['id'] ?>"
    class="status-btn shipped"
    style="
        display:inline-block;
        text-decoration:none;
        margin-bottom:6px;
    ">

    Delivery

</a>
                                        <?php foreach ($allowedStatuses as $nextStatus): ?>
                                            <?php if ($nextStatus === $status) continue; ?>
                                            <form method="post" class="status-form">
                                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                                <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                                                <button type="submit" name="update_status" class="status-btn <?= e($nextStatus) ?>">
                                                    <?= e(ucfirst($nextStatus)) ?>
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
$pageTitle = 'Admin Orders';
$actions = [
    [
        'href' => 'sales_details.php',
        'label' => 'Detailed Orders',
        'brand' => true,
    ],
];

require __DIR__ . '/layout.php';
