<?php
// payment.php
declare(strict_types=1);
session_start();
require 'db.php';

// ---------- Auth guard ----------
if (!isset($_SESSION['user_email'])) {
  header('Location: user_login.php');
  exit();
}
$user_email = $_SESSION['user_email'];

// ---------- CSRF ----------
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
  echo "<p>Invalid request. <a href='cart.php'>Back to cart</a></p>";
  exit();
}

// ---------- Input: selected cart item IDs ----------
if (!isset($_POST['selected_items']) || !is_array($_POST['selected_items'])) {
  echo "<p>No items selected. <a href='cart.php'>Back to cart</a></p>";
  exit();
}

// Sanitize selected IDs
$selected_items = array_values(array_unique(array_filter(
  array_map(static fn($v) => filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), $_POST['selected_items']),
  static fn($v) => $v !== false
)));
if (!$selected_items) {
  echo "<p>No valid items selected. <a href='cart.php'>Back to cart</a></p>";
  exit();
}

// ---------- OPTIONAL: Apply posted quantities for selected rows (safety belt) ----------
if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
  $upd = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_email = ?");
  foreach ($_POST['quantities'] as $cid => $qty) {
    $cid = filter_var($cid, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $qty = filter_var($qty, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
    if ($cid && $qty && in_array($cid, $selected_items, true)) {
      $upd->bind_param("iis", $qty, $cid, $user_email);
      $upd->execute();
    }
  }
  $upd->close();
}

// ---------- Query the selected rows with a safe dynamic IN (...) ----------
$placeholders = implode(',', array_fill(0, count($selected_items), '?'));
$sql = "
    SELECT 
        c.id AS cart_id,
        p.name,
        p.price,
        c.quantity,
        (p.price * c.quantity) AS line_total
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_email = ? AND c.id IN ($placeholders)
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo "Database error.";
  exit();
}

$types = 's' . str_repeat('i', count($selected_items)); // 1 string + N ints
$params = array_merge([$user_email], $selected_items);

$bind = [];
$bind[] = &$types;
foreach ($params as $i => $val) {
  $bind[] = &$params[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bind);

if (!$stmt->execute()) {
  echo "Failed to load items.";
  exit();
}
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$rows) {
  echo "<p>No matching items found. <a href='cart.php'>Back to cart</a></p>";
  exit();
}

// ---------- Totals ----------
$subtotal = 0.0;
foreach ($rows as $r) {
  $subtotal += (float)$r['line_total'];
}

$TAX_RATE = 0.0;          // 10% VAT
$FLAT_SHIPPING = 60.00;   // BDT flat shipping
$shipping = $subtotal > 0 ? $FLAT_SHIPPING : 0.0;
$tax = $subtotal * $TAX_RATE;
$grand_total = $subtotal + $shipping + $tax;

function bdt($n): string
{
  return number_format((float)$n, 2) . ' BDT';
}

// Keep a CSRF for next step (payment_process.php)
if (empty($_SESSION['pay_csrf'])) {
  $_SESSION['pay_csrf'] = bin2hex(random_bytes(32));
}
$pay_csrf = $_SESSION['pay_csrf'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Payment</title>
  <style>
    :root {
      --primary: #007bff;
      --success: #28a745;
      --danger: #dc3545;
      --bg: #f5f7fb;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --shadow: 0 10px 30px rgba(0, 0, 0, .06);
      --radius: 14px;
    }

    * {
      box-sizing: border-box
    }

    body {
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      margin: 0;
      background: var(--bg);
      color: var(--text);
    }

    .wrapper {
      max-width: 900px;
      margin: 36px auto;
      padding: 0 16px;
    }

    .headerRow {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
    }

    .h1 {
      font-size: 28px;
      margin: 0;
      font-weight: 700;
    }

    .backLink {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      background: var(--primary);
      color: #fff;
      padding: 10px 14px;
      border-radius: 10px;
      box-shadow: var(--shadow);
    }

    .backLink:hover {
      filter: brightness(.95)
    }

    .card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 18px;
    }

    .tableWrap {
      overflow: auto;
      border-radius: var(--radius);
      border: 1px solid #eef2f7;
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      min-width: 720px;
    }

    thead th {
      position: sticky;
      top: 0;
      background: #f3f6fc;
      color: #111;
      padding: 14px;
      font-weight: 700;
      border-bottom: 1px solid #e8eef7;
      text-align: left;
    }

    tbody td {
      padding: 14px;
      border-bottom: 1px solid #eef2f7;
      vertical-align: middle;
    }

    tbody tr:last-child td {
      border-bottom: none;
    }

    .badge {
      display: inline-block;
      background: #eef2f7;
      color: #111;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
    }

    .summary {
      margin-top: 16px;
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 8px 12px;
      align-items: center;
    }

    .summary .label {
      color: var(--muted);
    }

    .summary .value {
      justify-self: end;
      font-weight: 700;
    }

    .hr {
      grid-column: 1/-1;
      height: 1px;
      background: #eef2f7;
      margin: 4px 0;
    }

    .payRow {
      grid-column: 1/-1;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 6px;
      gap: 12px;
      flex-wrap: wrap;
    }

    .payBtn {
      background: var(--success);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 12px 16px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 700;
    }

    .payBtn:hover {
      filter: brightness(.96)
    }

    .note {
      font-size: 13px;
      color: var(--muted);
    }

    .meta {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
    }

    .meta .badge {
      background: #e7f3ff;
    }

    .footerHelp {
      margin-top: 12px;
      color: var(--muted);
      font-size: 13px;
    }
  </style>
</head>

<body>
  <div class="wrapper">
    <div class="headerRow">
      <h1 class="h1">Payment</h1>
      <a class="backLink" href="cart.php">← Back to cart</a>
    </div>

    <div class="card">
      <div class="meta" style="margin-bottom:12px;">
        <span class="badge">Secure Checkout</span>
        <span class="badge">Logged in as <?= htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <div class="tableWrap">
        <table aria-label="Items for payment">
          <thead>
            <tr>
              <th>Product</th>
              <th style="width:140px;">Price (BDT)</th>
              <th style="width:100px;">Qty</th>
              <th style="width:160px;">Line Total (BDT)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format((float)$row['price'], 2) ?></td>
                <td><?= (int)$row['quantity'] ?></td>
                <td><?= number_format((float)$row['line_total'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="summary" aria-label="Order summary">
        <div class="label">Subtotal</div>
        <div class="value"><?= bdt($subtotal) ?></div>

        <div class="label">Shipping</div>
        <div class="value"><?= bdt($shipping) ?></div>

        <div class="label">Vat (0%)</div>
        <div class="value"><?= bdt($tax) ?></div>

        <div class="hr"></div>

        <div class="label" style="font-size:18px;">Grand Total</div>
        <div class="value" style="font-size:18px;"><?= bdt($grand_total) ?></div>

        <div class="payRow">
          <form action="payment_place_order.php" method="post" style="margin:0;display:flex;gap:12px;flex-direction:column;">
            <?php foreach ($selected_items as $cid): ?>
              <input type="hidden" name="selected_items[]" value="<?= (int)$cid ?>">
            <?php endforeach; ?>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($pay_csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="grand_total" value="<?= htmlspecialchars((string)$grand_total, ENT_QUOTES, 'UTF-8') ?>">

            <label style="font-weight:700; margin-bottom:6px;">Select Payment Method:</label>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <label style="display:flex; align-items:center; gap:6px;">
                <input type="radio" name="payment_method" value="cod" required> Cash on Delivery
              </label>
              <label style="display:flex; align-items:center; gap:6px;">
                <input type="radio" name="payment_method" value="online" required> Pay Online
              </label>
            </div>

            <button type="submit" class="payBtn" style="margin-top:12px;">Proceed to Pay</button>
            <span class="note">You’ll confirm your payment method on the next step.</span>
          </form>
        </div>

      </div>
    </div>
  </div>
</body>

</html>