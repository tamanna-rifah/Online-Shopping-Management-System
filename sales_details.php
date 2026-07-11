<?php
// sales_details.php — Sales & Orders (list + detail) using layout.php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
  header('Location: admin_login.php');
  exit();
}

require __DIR__ . '/db.php';

// Helpers
function e(string $v): string
{
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

function money(float $v): string
{
  return number_format($v, 2);
}

$validStatuses = ['pending', 'processing', 'paid', 'shipped', 'delivered', 'cancelled'];

// Inputs
$orderId     = isset($_GET['order']) ? max(0, (int)$_GET['order']) : 0;
$qStatus     = isset($_GET['status']) && in_array($_GET['status'], $validStatuses, true) ? $_GET['status'] : '';
$qEmail      = isset($_GET['email']) ? trim((string)$_GET['email']) : '';
$qFrom       = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
$qTo         = isset($_GET['to']) ? trim((string)$_GET['to']) : '';
$export      = isset($_GET['export']) ? (int)$_GET['export'] : 0;
$page        = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$perPage     = 12;
$offset      = ($page - 1) * $perPage;

// Build WHERE
$where = [];
$params = [];
$types  = '';

if ($qStatus !== '') {
  $where[] = 'o.status = ?';
  $params[] = $qStatus;
  $types .= 's';
}
if ($qEmail  !== '') {
  $where[] = 'o.user_email LIKE ?';
  $params[] = '%' . $qEmail . '%';
  $types .= 's';
}
if ($qFrom   !== '') {
  $where[] = 'o.created_at >= ?';
  $params[] = $qFrom . ' 00:00:00';
  $types .= 's';
}
if ($qTo     !== '') {
  $where[] = 'o.created_at <= ?';
  $params[] = $qTo   . ' 23:59:59';
  $types .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// KPIs
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

$kpi = [
  'today_sales' => 0.0,
  'today_orders' => 0,
  'month_sales' => 0.0,
  'month_orders' => 0,
  'lifetime_sales' => 0.0,
  'lifetime_orders' => 0
];

// Today
$stmt = $conn->prepare("SELECT COALESCE(SUM(grand_total),0), COUNT(*) FROM orders WHERE DATE(created_at)=?");
$stmt->bind_param('s', $today);
$stmt->execute();
$stmt->bind_result($kpi['today_sales'], $kpi['today_orders']);
$stmt->fetch();
$stmt->close();

// Month
$stmt = $conn->prepare("SELECT COALESCE(SUM(grand_total),0), COUNT(*) FROM orders WHERE DATE(created_at) >= ?");
$stmt->bind_param('s', $monthStart);
$stmt->execute();
$stmt->bind_result($kpi['month_sales'], $kpi['month_orders']);
$stmt->fetch();
$stmt->close();

// Lifetime
$res = $conn->query("SELECT COALESCE(SUM(grand_total),0) AS s, COUNT(*) AS c FROM orders");
if ($res && $row = $res->fetch_assoc()) {
  $kpi['lifetime_sales']  = (float)$row['s'];
  $kpi['lifetime_orders'] = (int)$row['c'];
}
$res?->close();

// Status counts (for quick glance)
$statusCounts = array_fill_keys($validStatuses, 0);
$res = $conn->query("SELECT status, COUNT(*) c FROM orders GROUP BY status");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $statusCounts[(string)$r['status']] = (int)$r['c'];
  }
  $res->close();
}

// If detail view requested
$order = null;
$orderItems = [];
$address = null;
$payment = null;

if ($orderId > 0) {
  $stmt = $conn->prepare("
    SELECT o.*, COALESCE(u.name,'') AS customer_name, u.phone AS user_phone
    FROM orders o
    LEFT JOIN users u ON u.email = o.user_email
    WHERE o.id = ?
  ");
  $stmt->bind_param('i', $orderId);
  $stmt->execute();
  $order = $stmt->get_result()?->fetch_assoc();
  $stmt->close();

  if ($order) {
    // Items
    $stmt = $conn->prepare("
      SELECT oi.*, p.name AS product_name, p.category
      FROM order_items oi
      JOIN products p ON p.id = oi.product_id
      WHERE oi.order_id = ?
      ORDER BY oi.id ASC
    ");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $orderItems = $stmt->get_result()?->fetch_all(MYSQLI_ASSOC) ?? [];
    $stmt->close();

    // Address (default shipping — best guess by email)
    $stmt = $conn->prepare("
      SELECT * FROM addresses
      WHERE user_email = ?
      ORDER BY is_default DESC, created_at DESC
      LIMIT 1
    ");
    $stmt->bind_param('s', $order['user_email']);
    $stmt->execute();
    $address = $stmt->get_result()?->fetch_assoc();
    $stmt->close();

    // Payment (latest for the order)
    $stmt = $conn->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $payment = $stmt->get_result()?->fetch_assoc();
    $stmt->close();
  }
}

// CSV export (list context, not detail)
if ($export === 1 && $orderId === 0) {
  $sql = "
    SELECT o.id, o.user_email, o.status, o.payment_method, o.created_at, o.subtotal, o.shipping, o.grand_total
    FROM orders o
    $whereSql
    ORDER BY o.created_at DESC, o.id DESC
  ";
  $stmt = $conn->prepare($sql);
  if ($params) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $res = $stmt->get_result();

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=orders_export_' . date('Ymd_His') . '.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Order ID', 'Email', 'Status', 'Payment Method', 'Created At', 'Subtotal', 'Shipping', 'Grand Total']);
  while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
      $row['id'],
      $row['user_email'],
      $row['status'],
      $row['payment_method'],
      $row['created_at'],
      $row['subtotal'],
      $row['shipping'],
      $row['grand_total']
    ]);
  }
  fclose($out);
  exit;
}

// List (with filters + pagination) if not in detail or show below detail
$totalRows = 0;
if ($orderId === 0) {
  $countSql = "SELECT COUNT(*) FROM orders o $whereSql";
  $stmt = $conn->prepare($countSql);
  if ($params) {
    $stmt->bind_param($types, ...$params);
  }
  $stmt->execute();
  $stmt->bind_result($totalRows);
  $stmt->fetch();
  $stmt->close();
}

$list = [];
if ($orderId === 0) {
  $sql = "
    SELECT o.id, o.user_email, COALESCE(u.name,'') AS customer_name, o.created_at, o.status, o.payment_method, o.subtotal, o.shipping, o.grand_total
    FROM orders o
    LEFT JOIN users u ON u.email = o.user_email
    $whereSql
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT ? OFFSET ?
  ";
  $stmt = $conn->prepare($sql);
  if ($params) {
    $types2 = $types . 'ii';
    $stmt->bind_param($types2, ...array_merge($params, [$perPage, $offset]));
  } else {
    $stmt->bind_param('ii', $perPage, $offset);
  }
  $stmt->execute();
  $res = $stmt->get_result();
  $list = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
}

ob_start();
?>
<div class="grid">
  <!-- KPIs -->
  <div class="card col-12" role="region" aria-label="KPIs">
    <h3>Sales Overview</h3>
    <div class="grid" style="grid-template-columns: repeat(12,1fr); gap: 12px;">
      <div class="card" style="grid-column: span 3;">
        <div style="font-weight:800; color:var(--muted);">Today Sales</div>
        <div style="font-size:1.6rem; font-weight:900; margin-top:6px;">Tk <?= money((float)$kpi['today_sales']) ?></div>
        <div class="chip" style="margin-top:8px;"><span class="dot"></span> Orders: <?= (int)$kpi['today_orders'] ?></div>
      </div>
      <div class="card" style="grid-column: span 3;">
        <div style="font-weight:800; color:var(--muted);">This Month</div>
        <div style="font-size:1.6rem; font-weight:900; margin-top:6px;">Tk <?= money((float)$kpi['month_sales']) ?></div>
        <div class="chip" style="margin-top:8px;"><span class="dot"></span> Orders: <?= (int)$kpi['month_orders'] ?></div>
      </div>
      <div class="card" style="grid-column: span 3;">
        <div style="font-weight:800; color:var(--muted);">Lifetime Sales</div>
        <div style="font-size:1.6rem; font-weight:900; margin-top:6px;">Tk <?= money((float)$kpi['lifetime_sales']) ?></div>
        <div class="chip" style="margin-top:8px;"><span class="dot"></span> Orders: <?= (int)$kpi['lifetime_orders'] ?></div>
      </div>
      <div class="card" style="grid-column: span 3;">
        <div style="font-weight:800; color:var(--muted);">By Status</div>
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px;">
          <?php foreach ($statusCounts as $st => $cnt): ?>
            <span class="badge<?= $st === 'delivered' ? ' badge--ok' : (($st === 'pending' || $st === 'cancelled') ? ' badge--warn' : '') ?>">
              <?= e(ucfirst($st)) ?>: <?= (int)$cnt ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- If DETAIL -->
  <?php if ($orderId > 0 && $order): ?>
    <div class="card col-12" role="region" aria-label="Order detail">
      <h3>Order #<?= (int)$order['id'] ?> — <?= e($order['user_email']) ?></h3>
      <div style="display:grid; grid-template-columns: 2fr 1fr; gap:18px;">
        <div class="card" style="background:var(--panel-2);">
          <div style="font-weight:800; margin-bottom:8px;">Items</div>
          <table class="table" aria-label="Items">
            <thead>
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Line Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$orderItems): ?>
                <tr>
                  <td colspan="5" style="color:var(--muted);">No items found.</td>
                </tr>
                <?php else: foreach ($orderItems as $it):
                  $line = (float)$it['quantity'] * (float)$it['price']; ?>
                  <tr>
                    <td><?= e((string)$it['product_name']) ?></td>
                    <td><span class="badge"><?= e((string)$it['category']) ?></span></td>
                    <td><?= (int)$it['quantity'] ?></td>
                    <td>Tk <?= money((float)$it['price']) ?></td>
                    <td>Tk <?= money($line) ?></td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>

        <div class="card" style="background:var(--panel-2);">
          <div style="font-weight:800; margin-bottom:8px;">Summary</div>
          <div>Status: <span class="badge<?= strtolower((string)$order['status']) === 'delivered' ? ' badge--ok' : '' ?>"><?= e((string)$order['status']) ?></span></div>
          <div>Payment: <span class="badge"><?= e(strtoupper((string)$order['payment_method'])) ?></span></div>
          <div style="margin-top:8px;">Date: <?= e(date('Y-m-d H:i', strtotime((string)$order['created_at']))) ?></div>
          <hr style="border-color:var(--border);">
          <div style="font-weight:900; font-size:1.2rem;">Grand Total: $<?= money((float)$order['grand_total']) ?></div>

          <form action="update_status.php" method="post" style="margin-top:10px;">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
            <label for="st" style="display:block; margin-bottom:6px; color:var(--muted); font-weight:800;">Update Status</label>
            <select id="st" name="status" style="width:100%; padding:10px; border-radius:10px; background:var(--panel); color:var(--text); border:1px solid var(--border);">
              <?php foreach ($validStatuses as $st): ?>
                <option value="<?= e($st) ?>" <?= $st === $order['status'] ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn--brand" style="margin-top:10px; width:100%;">Save</button>
          </form>
        </div>

        <div class="card" style="grid-column: span 2; background:var(--panel-2);">
          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px;">
            <div>
              <div style="font-weight:800; margin-bottom:8px;">Customer</div>
              <div>Name: <?= e((string)$order['customer_name']) ?: '—' ?></div>
              <div>Email: <?= e((string)$order['user_email']) ?></div>
              <div>Phone: <?= e((string)($order['user_phone'] ?? '')) ?: '—' ?></div>
            </div>
            <div>
              <div style="font-weight:800; margin-bottom:8px;">Shipping Address</div>
              <?php if ($address): ?>
                <div><?= e((string)$address['name']) ?></div>
                <div><?= e((string)$address['phone']) ?></div>
                <div><?= e((string)$address['line1']) ?> <?= e((string)($address['line2'] ?? '')) ?></div>
                <div><?= e((string)$address['city']) ?>, <?= e((string)($address['state'] ?? '')) ?> <?= e((string)($address['postal_code'] ?? '')) ?></div>
                <div><?= e((string)$address['country']) ?></div>
              <?php else: ?>
                <div style="color:var(--muted);">No address on file.</div>
              <?php endif; ?>
            </div>
            <div>
              <div style="font-weight:800; margin-bottom:8px;">Payment</div>
              <?php if ($payment): ?>
                <div>Method: <?= e(strtoupper((string)$payment['method'])) ?></div>
                <div>Status: <span class="badge"><?= e((string)$payment['status']) ?></span></div>
                <div>Amount: $<?= money((float)$payment['amount']) ?></div>
                <div>Txn ID: <?= e((string)($payment['transaction_id'] ?? '—')) ?></div>
                <div>Verified: <?= e((string)$payment['verified']) ?></div>
                <div>Date: <?= e(date('Y-m-d H:i', strtotime((string)$payment['created_at']))) ?></div>
              <?php else: ?>
                <div style="color:var(--muted);">No payment record.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <div style="margin-top:10px;">
        <a class="btn" href="sales_details.php">← Back to list</a>
        <a class="btn btn--brand" href="sales_details.php?export=1<?= $qStatus || $qEmail || $qFrom || $qTo ? '&' . http_build_query(['status' => $qStatus, 'email' => $qEmail, 'from' => $qFrom, 'to' => $qTo]) : '' ?>">Export CSV</a>
      </div>
    </div>
  <?php endif; ?>

  <!-- LIST (with filters) -->
  <div class="card col-12" role="region" aria-label="Orders list">
    <h3>Orders</h3>
    <form method="get" style="display:grid; grid-template-columns: repeat(12,1fr); gap:10px; margin-bottom:12px;">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <div style="grid-column: span 3;">
        <label style="display:block; font-weight:800; color:var(--muted); margin-bottom:6px;">Status</label>
        <select name="status" style="width:100%; padding:10px; border-radius:10px; background:var(--panel); color:var(--text); border:1px solid var(--border);">
          <option value="">All</option>
          <?php foreach ($validStatuses as $st): ?>
            <option value="<?= e($st) ?>" <?= $st === $qStatus ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="grid-column: span 3;">
        <label style="display:block; font-weight:800; color:var(--muted); margin-bottom:6px;">Customer Email</label>
        <input name="email" value="<?= e($qEmail) ?>" placeholder="user@example.com"
          style="width:100%; padding:10px; border-radius:10px; background:var(--panel); color:var(--text); border:1px solid var(--border);" />
      </div>
      <div style="grid-column: span 2;">
        <label style="display:block; font-weight:800; color:var(--muted); margin-bottom:6px;">From</label>
        <input type="date" name="from" value="<?= e($qFrom) ?>"
          style="width:100%; padding:10px; border-radius:10px; background:var(--panel); color:var(--text); border:1px solid var(--border);" />
      </div>
      <div style="grid-column: span 2;">
        <label style="display:block; font-weight:800; color:var(--muted); margin-bottom:6px;">To</label>
        <input type="date" name="to" value="<?= e($qTo) ?>"
          style="width:100%; padding:10px; border-radius:10px; background:var(--panel); color:var(--text); border:1px solid var(--border);" />
      </div>
      <div style="grid-column: span 2; display:flex; align-items:flex-end; gap:8px;">
        <button class="btn btn--brand" type="submit">Filter</button>
        <a class="btn" href="sales_details.php">Reset</a>
      </div>
    </form>

    <div style="display:flex; gap:8px; margin-bottom:8px;">
      <a class="btn btn--brand" href="sales_details.php?export=1<?= $qStatus || $qEmail || $qFrom || $qTo ? '&' . http_build_query(['status' => $qStatus, 'email' => $qEmail, 'from' => $qFrom, 'to' => $qTo]) : '' ?>">Export CSV</a>
    </div>

    <table class="table" aria-label="Orders table">
      <thead>
        <tr>
          <th>#</th>
          <th>Customer</th>
          <th>Date</th>
          <th>Status</th>
          <th>Pay</th>

          <th>Total</th>

        </tr>
      </thead>
      <tbody>
        <?php if ($orderId === 0 && !$list): ?>
          <tr>
            <td colspan="9" style="color:var(--muted);">No matching orders.</td>
          </tr>
          <?php elseif ($orderId === 0): foreach ($list as $r):
            $oid = (int)$r['id'];
            $name = trim((string)$r['customer_name']);
            $customer = $name !== '' ? $name : (string)$r['user_email'];
            $date = $r['created_at'] ? date('Y-m-d H:i', strtotime((string)$r['created_at'])) : '—';
            $status = (string)$r['status'];
            $statusLower = strtolower($status);
            $badgeClass = 'badge';
            if ($statusLower === 'delivered') $badgeClass = 'badge badge--ok';
            elseif ($statusLower === 'pending' || $statusLower === 'cancelled') $badgeClass = 'badge badge--warn';
          ?>
            <tr>
              <td><a class="link" href="sales_details.php?order=<?= $oid ?>">#<?= $oid ?></a></td>
              <td><?= e($customer) ?></td>
              <td><?= e($date) ?></td>
              <td><span class="<?= $badgeClass ?>"><?= e($status) ?></span></td>
              <td><span class="badge"><?= e(strtoupper((string)$r['payment_method'])) ?></span></td>

              <td style="font-weight:900;">Tk <?= money((float)$r['grand_total']) ?></td>

            </tr>
        <?php endforeach;
        endif; ?>
      </tbody>
    </table>

    <?php if ($orderId === 0):
      $totalPages = max(1, (int)ceil($totalRows / $perPage));
      if ($totalPages > 1):
        $qs = $_GET;
        unset($qs['page']);
        $base = 'sales_details.php?' . http_build_query($qs);
    ?>
        <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="btn <?= $p === $page ? 'btn--brand' : '' ?>" href="<?= e($base . '&page=' . $p) ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
    <?php endif;
    endif; ?>

  </div>
</div>
<?php
$content = ob_get_clean();

$pageTitle = $orderId > 0 && $order ? ("Order #" . $orderId) : 'Sales & Orders';
$actions = [
  ['href' => 'sales_details.php?export=1' . ($qStatus || $qEmail || $qFrom || $qTo ? '&' . http_build_query(['status' => $qStatus, 'email' => $qEmail, 'from' => $qFrom, 'to' => $qTo]) : ''), 'label' => 'Export CSV', 'brand' => true],
];

include __DIR__ . '/layout.php';
