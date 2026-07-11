<?php

declare(strict_types=1);
session_start();
require 'db.php';

// ---- Auth guard ----
if (!isset($_SESSION['user_email'])) {
  header('Location: user_login.php');
  exit();
}
$user_email = $_SESSION['user_email'];

// ---- Validate selected items (from previous cart page) ----
if (!isset($_POST['selected_items']) || !is_array($_POST['selected_items'])) {
  echo "<p>No items selected. <a href='cart.php'>Back to cart</a></p>";
  exit();
}

$selected_items = array_values(array_unique(array_filter(
  array_map(static fn($v) => filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), $_POST['selected_items']),
  static fn($v) => $v !== false
)));

if (!$selected_items) {
  echo "<p>No valid items selected. <a href='cart.php'>Back to cart</a></p>";
  exit();
}

// ---- Re-query selected cart rows and compute totals ----
$placeholders = implode(',', array_fill(0, count($selected_items), '?'));
$sql = "
    SELECT 
        c.id AS cart_id,
        c.product_id,
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
  die("Database error.");
}

$types = 's' . str_repeat('i', count($selected_items));
$params = array_merge([$user_email], $selected_items);
$bind = [];
$bind[] = &$types;
foreach ($params as $i => $val) {
  $bind[] = &$params[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bind);

$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$rows) {
  echo "<p>No matching items found. <a href='cart.php'>Back to cart</a></p>";
  exit();
}

// ---- Totals ----
$subtotal = 0.0;
foreach ($rows as $r) {
  $subtotal += (float)$r['line_total'];
}
$TAX_RATE = 0.00;
$FLAT_SHIPPING = 60.00;
$shipping = $subtotal > 0 ? $FLAT_SHIPPING : 0.0;
$tax = $subtotal * $TAX_RATE;
$grand_total = $subtotal + $shipping + $tax;

// ---- CSRF token for the form ----
if (!isset($_SESSION['pay_csrf'])) {
  $_SESSION['pay_csrf'] = bin2hex(random_bytes(32));
}
$pay_csrf = $_SESSION['pay_csrf'];

// ---- Save snapshot for order placement ----
$_SESSION['checkout_snapshot'] = [
  'user_email'   => $user_email,
  'selected_ids' => $selected_items,
  'items'        => $rows,
  'subtotal'     => $subtotal,
  'shipping'     => $shipping,
  'tax'          => $tax,
  'grand_total'  => $grand_total,
  'csrf'         => $pay_csrf,
  'created_at'   => time(),
];

// ---- Helper function ----
function bdt($n): string
{
  return number_format((float)$n, 2) . ' BDT';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Select Payment Method</title>
  <style>
    :root {
      --primary: #007bff;
      --success: #28a745;
      --danger: #dc3545;
      --bg: #f5f7fb;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --radius: 14px;
      --shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
    }

    * {
      box-sizing: border-box;
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

    .h1 {
      font-size: 28px;
      margin: 0 0 12px;
      font-weight: 700;
    }

    .card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 24px;
    }

    .tableWrap {
      overflow-x: auto;
      border-radius: var(--radius);
      border: 1px solid #eef2f7;
      margin-bottom: 16px;
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

    .summary {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 8px 12px;
      align-items: center;
      margin-top: 12px;
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

    .payBox {
      display: grid;
      gap: 12px;
      margin-top: 16px;
    }

    .opt {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.2s ease-in-out;
    }

    .opt:hover {
      border-color: var(--primary);
      background: #f0f7ff;
    }

    .opt input {
      transform: scale(1.2);
    }

    .actions {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-top: 16px;
      flex-wrap: wrap;
    }

    .btn {
      background: var(--success);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 12px 16px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.2s ease;
    }

    .btn:hover {
      opacity: 0.9;
    }

    .link {
      color: #fff;
      text-decoration: none;
    }

    .back {
      background: var(--primary);
    }

    .note {
      font-size: 13px;
      color: var(--muted);
    }

    @media(max-width: 768px) {
      table {
        min-width: 100%;
      }

      .actions {
        flex-direction: column;
        align-items: stretch;
      }
    }
  </style>

</head>

<body>
  <div class="wrapper">
    <h1 class="h1">Choose Payment Method</h1>
    <div class="card">

      <div class="tableWrap">
        <table aria-label="Items">
          <thead>
            <tr>
              <th>Product</th>
              <th style="width:140px;">Price</th>
              <th style="width:100px;">Qty</th>
              <th style="width:160px;">Line Total</th>
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
        <div class="label">VAT (0%)</div>
        <div class="value"><?= bdt($tax) ?></div>
        <div class="hr"></div>
        <div class="label" style="font-size:18px;">Grand Total</div>
        <div class="value" style="font-size:18px;"><?= bdt($grand_total) ?></div>
      </div>

      <form class="payBox" action="payment_place_order.php" method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($pay_csrf, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($selected_items as $cid): ?>
          <input type="hidden" name="selected_items[]" value="<?= (int)$cid ?>">
        <?php endforeach; ?>
        <input type="hidden" name="grand_total" value="<?= htmlspecialchars((string)$grand_total, ENT_QUOTES, 'UTF-8') ?>">

        <label class="opt">
          <input type="radio" name="payment_method" value="online" required>
          <span><strong>Pay Online</strong> — Card / Mobile Wallet (secure gateway)</span>
        </label>

        <label class="opt">
          <input type="radio" name="payment_method" value="cod" required>
          <span><strong>Cash on Delivery</strong> — Pay when your order arrives</span>
        </label>

        <div class="actions">
          <button class="btn" type="submit">Continue</button>
          <a class="btn back link" href="cart.php">← Back to cart</a>
          <span class="note">You’ll confirm your choice on the next step.</span>
        </div>
      </form>


    </div>
  </div>
</body>

</html>