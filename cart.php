<?php
// cart.php (single-container)
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
start_auth_session();
require 'db.php';

// ---------- Auth ----------
if (!isset($_SESSION['user_email'])) {
  header('Location: user_login.php');
  exit();
}
$user_email = $_SESSION['user_email'];

// ---------- CSRF ----------
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// ---------- Helpers ----------
function send_json($arr, $code = 200)
{
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($arr);
  exit();
}

// ---------- AJAX: update/remove/bulk_update ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    send_json(['ok' => false, 'message' => 'Invalid CSRF token'], 403);
  }

  $action  = $_POST['action'] ?? '';

  if ($action === 'update') {
    $cart_id = $_POST['cart_id'] ?? '';
    if (!ctype_digit((string)$cart_id)) send_json(['ok' => false, 'message' => 'Invalid cart item'], 400);

    $qty = (int)($_POST['quantity'] ?? 0);
    if ($qty < 1) $qty = 1;

    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_email = ?");
    $stmt->bind_param("iis", $qty, $cart_id, $user_email);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) send_json(['ok' => false, 'message' => 'Update failed']);

    // fetch back the authoritative line
    $q = $conn->prepare("
            SELECT p.price, c.quantity, (p.price * c.quantity) AS line_total
            FROM cart c JOIN products p ON c.product_id = p.id
            WHERE c.id = ? AND c.user_email = ?
        ");
    $q->bind_param("is", $cart_id, $user_email);
    $q->execute();
    $res = $q->get_result()->fetch_assoc();
    $q->close();

    // subtotal
    $subq = $conn->prepare("
            SELECT COALESCE(SUM(p.price * c.quantity),0) AS subtotal,
                   COALESCE(SUM(c.quantity),0) AS item_count
            FROM cart c JOIN products p ON c.product_id = p.id
            WHERE c.user_email = ?
        ");
    $subq->bind_param("s", $user_email);
    $subq->execute();
    $subres = $subq->get_result()->fetch_assoc();
    $subq->close();

    send_json([
      'ok' => true,
      'message' => 'Quantity updated',
      'line_total' => (float)$res['line_total'],
      'price' => (float)$res['price'],
      'quantity' => (int)$res['quantity'],
      'subtotal' => (float)$subres['subtotal'],
      'item_count' => (int)$subres['item_count'],
    ]);
  }

  if ($action === 'remove') {
    $cart_id = $_POST['cart_id'] ?? '';
    if (!ctype_digit((string)$cart_id)) send_json(['ok' => false, 'message' => 'Invalid cart item'], 400);

    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_email = ?");
    $stmt->bind_param("is", $cart_id, $user_email);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) send_json(['ok' => false, 'message' => 'Remove failed']);

    $subq = $conn->prepare("
            SELECT COALESCE(SUM(p.price * c.quantity),0) AS subtotal,
                   COALESCE(SUM(c.quantity),0) AS item_count
            FROM cart c JOIN products p ON c.product_id = p.id
            WHERE c.user_email = ?
        ");
    $subq->bind_param("s", $user_email);
    $subq->execute();
    $subres = $subq->get_result()->fetch_assoc();
    $subq->close();

    send_json([
      'ok' => true,
      'message' => 'Item removed',
      'subtotal' => (float)$subres['subtotal'],
      'item_count' => (int)$subres['item_count'],
    ]);
  }

  if ($action === 'bulk_update') {
    // Expect payload: quantities[cart_id] = qty for selected rows
    $updated = 0;
    if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
      $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_email = ?");
      foreach ($_POST['quantities'] as $cid => $qty) {
        $cid = filter_var($cid, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qty = filter_var($qty, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 9999]]);
        if ($cid && $qty) {
          $stmt->bind_param("iis", $qty, $cid, $user_email);
          $stmt->execute();
          $updated += $stmt->affected_rows >= 0 ? 1 : 0;
        }
      }
      $stmt->close();
    }
    send_json(['ok' => true, 'updated' => $updated]);
  }

  send_json(['ok' => false, 'message' => 'Unknown action'], 400);
}

// ---------- Fetch cart items ----------
$query = $conn->prepare("
    SELECT 
        c.id, c.product_id, c.quantity,
        p.name, p.price, p.image,
        (p.price * c.quantity) AS total
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_email = ?
    ORDER BY c.id DESC
");
$query->bind_param("s", $user_email);
$query->execute();
$result = $query->get_result();

$items = [];
$subtotal = 0.0;
$item_count = 0;
while ($row = $result->fetch_assoc()) {
  $items[] = $row;
  $subtotal += (float)$row['total'];
  $item_count += (int)$row['quantity'];
}
$query->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Your Cart</title>
  <style>
    :root {
      --primary: #4f46e5;
      --danger: #ef4444;
      --success: #22c55e;
      --warning: #f59e0b;
      --bg: #f9fafb;
      --card: #ffffffcc;
      --text: #111827;
      --muted: #6b7280;
      --shadow: 0 8px 24px rgba(0, 0, 0, .08);
      --radius: 16px;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: "Inter", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      margin: 0;
      background: var(--bg);
      color: var(--text);
      line-height: 1.5;
    }

    .wrapper {
      max-width: 1200px;
      margin: 40px auto;
      padding: 0 20px;
    }

    /* Header */
    .headerRow {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
    }

    .h1 {
      font-size: 30px;
      margin: 0;
      font-weight: 700;
      color: var(--primary);
    }

    .backLink {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      background: var(--primary);
      color: #fff;
      padding: 10px 16px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      transition: all .2s ease;
    }

    .backLink:hover {
      transform: translateY(-2px);
      filter: brightness(.95);
    }

    /* Card container */
    .card {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      backdrop-filter: blur(8px);
    }

    /* Cart */
    .cartCard {
      padding: 20px;
    }

    .tableWrap {
      overflow: auto;
      border-radius: var(--radius);
      border: 1px solid #e5e7eb;
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      min-width: 760px;
    }

    thead th {
      position: sticky;
      top: 0;
      background: #f3f4f6;
      color: #111;
      padding: 14px;
      font-weight: 600;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: .5px;
      border-bottom: 1px solid #e5e7eb;
    }

    tbody td {
      padding: 14px;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
    }

    tbody tr:hover {
      background: #f9fafb;
      transition: background .2s;
    }

    tbody tr:last-child td {
      border-bottom: none;
    }

    /* Product */
    .rowImg {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      object-fit: cover;
      border: 1px solid #e5e7eb;
      background: #fff;
    }

    .prodCell {
      display: flex;
      align-items: center;
      gap: 14px;
      font-weight: 600;
    }

    .price,
    .lineTotal {
      white-space: nowrap;
      font-weight: 500;
    }

    /* Quantity */
    .qtyWrap {
      display: inline-flex;
      align-items: center;
      border: 1px solid #d1d5db;
      border-radius: 10px;
      overflow: hidden;
    }

    .qtyBtn {
      background: #f3f4f6;
      border: none;
      width: 34px;
      height: 34px;
      cursor: pointer;
      font-weight: 700;
      font-size: 16px;
      transition: background .2s;
    }

    .qtyBtn:hover {
      background: #e5e7eb;
    }

    .qtyInput {
      width: 54px;
      height: 34px;
      border: none;
      text-align: center;
      outline: none;
      font-weight: 600;
      font-size: 14px;
    }

    /* Actions */
    .actions {
      display: flex;
      gap: 8px;
    }

    .btn {
      border: none;
      border-radius: 10px;
      padding: 8px 14px;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
      transition: transform .15s, filter .2s;
    }

    .btn:hover {
      transform: translateY(-2px);
    }

    .btn-remove {
      background: var(--danger);
      color: #fff;
    }

    .btn-update {
      background: var(--success);
      color: #fff;
    }

    /* Summary */
    .inlineSummary {
      margin-top: 20px;
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 8px 12px;
      align-items: center;
      font-size: 15px;
    }

    .inlineSummary .label {
      color: var(--muted);
    }

    .inlineSummary .value {
      justify-self: end;
      font-weight: 700;
    }

    .hr {
      grid-column: 1/-1;
      height: 1px;
      background: #e5e7eb;
      margin: 8px 0;
    }

    /* Checkout row */
    .checkoutRow {
      grid-column: 1/-1;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 8px;
    }

    .payBtn {
      background: var(--success);
      color: #fff;
      border: none;
      border-radius: 14px;
      padding: 12px 20px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 700;
      transition: all .2s ease;
    }

    .payBtn:hover:not(:disabled) {
      transform: translateY(-2px);
      filter: brightness(.95);
    }

    .payBtn:disabled {
      opacity: .6;
      cursor: not-allowed;
    }

    .note {
      font-size: 13px;
      color: var(--muted);
    }

    /* Empty cart */
    .empty {
      padding: 50px;
      text-align: center;
      color: var(--muted);
    }

    .empty h3 {
      margin: 0 0 10px 0;
      font-size: 22px;
      font-weight: 600;
    }

    .shopMore {
      display: inline-block;
      margin-top: 16px;
      background: var(--primary);
      color: #fff;
      text-decoration: none;
      padding: 12px 18px;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      font-weight: 600;
      transition: all .2s ease;
    }

    .shopMore:hover {
      transform: translateY(-2px);
      filter: brightness(.95);
    }

    /* Toast */
    .toast {
      position: fixed;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%);
      background: #111827;
      color: #fff;
      padding: 12px 18px;
      border-radius: 12px;
      box-shadow: var(--shadow);
      opacity: 0;
      pointer-events: none;
      transition: opacity .25s, transform .25s;
      z-index: 9999;
    }

    .toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(-6px);
    }

    .toast.error {
      background: var(--danger);
    }

    .badgePill {
      background: #f3f4f6;
      color: #111;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 13px;
      font-weight: 600;
    }
  </style>
</head>

<body>
  <div class="wrapper">
    <div class="headerRow">
      <h1 class="h1">🛒 Your Cart</h1>
      <a class="backLink" href="user_dashboard.php">← Back to Dashboard</a>
    </div>

    <?php if (count($items) === 0): ?>
      <div class="card empty">
        <h3>Your cart is empty</h3>
        <p>Add products from the dashboard to see them here.</p>
        <a href="user_dashboard.php" class="shopMore">Browse Products</a>
      </div>
    <?php else: ?>
      <div class="card cartCard">
        <div class="topBar">
          <label class="selectAll">
            <input type="checkbox" id="select-all">
            <span>Select all</span>
          </label>
          <div class="badge">
            <span class="badgePill" id="badge-count"><?= (int)$item_count ?> items</span>
          </div>
        </div>

        <div class="tableWrap">
          <table id="cart-table" aria-label="Cart items">
            <thead>
              <tr>
                <th style="width:48px;">Pick</th>
                <th>Product</th>
                <th>Price (BDT)</th>
                <th style="min-width:160px;">Quantity</th>
                <th>Total (BDT)</th>
                <th style="width:170px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr data-cart-id="<?= (int)$it['id'] ?>" data-price="<?= htmlspecialchars($it['price']) ?>">
                  <td>
                    <input type="checkbox" class="row-check" value="<?= (int)$it['id'] ?>" checked>
                  </td>
                  <td>
                    <div class="prodCell">
                      <?php if (!empty($it['image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($it['image'], ENT_QUOTES, 'UTF-8') ?>" alt="" class="rowImg" />
                      <?php else: ?>
                        <div class="rowImg" style="display:flex;align-items:center;justify-content:center;color:#aaa;">—</div>
                      <?php endif; ?>
                      <div><?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                  </td>
                  <td class="price"><?= number_format((float)$it['price'], 2) ?></td>
                  <td>
                    <div class="qtyWrap">
                      <button class="qtyBtn minus" type="button">−</button>
                      <input class="qtyInput" type="number" min="1" value="<?= (int)$it['quantity'] ?>">
                      <button class="qtyBtn plus" type="button">+</button>
                    </div>
                  </td>
                  <td class="lineTotal" data-line-total="<?= number_format((float)$it['total'], 2, '.', '') ?>">
                    <?= number_format((float)$it['total'], 2) ?>
                  </td>
                  <td class="actions">
                    <button class="btn btn-remove" type="button">Remove</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Inline summary + payment inside SAME container -->
        <div class="inlineSummary" aria-label="Order summary">
          <div class="label">Cart Subtotal</div>
          <div class="value" id="subtotal-text"><?= number_format($subtotal, 2) ?> BDT</div>

          <div class="label">Selected Items</div>
          <div class="value" id="selected-count">0</div>

          <div class="label"><strong>Selected Total</strong></div>
          <div class="value" id="selected-total">0.00 BDT</div>

          <div class="hr"></div>

          <div class="checkoutRow">
            <form method="POST" action="payment.php" id="pay-form" style="margin:0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CSRF) ?>">
              <!-- JS will inject selected_items[] and quantities[cartId] -->
              <button type="submit" class="payBtn" id="pay-btn" disabled>Proceed to Payment</button>
            </form>
            <span class="note">Pick only the items you want to pay for now.</span>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div id="toast" class="toast" role="status" aria-live="polite"></div>

  <script>
    // ---------- Helpers ----------
    const fmtBDT = (n) => {
      try {
        return new Intl.NumberFormat('en-BD', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }).format(n) + ' BDT';
      } catch {
        return Number(n).toFixed(2) + ' BDT';
      }
    };
    const fmtPlain = (n) => {
      try {
        return new Intl.NumberFormat('en-BD', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }).format(n);
      } catch {
        return Number(n).toFixed(2);
      }
    };

    function showToast(msg, isError = false) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.toggle('error', !!isError);
      t.classList.add('show');
      clearTimeout(window.__toastTimer);
      window.__toastTimer = setTimeout(() => t.classList.remove('show'), 1700);
    }

    // ---------- Elements ----------
    const subtotalText = document.getElementById('subtotal-text');
    const selectedCountEl = document.getElementById('selected-count');
    const selectedTotalEl = document.getElementById('selected-total');
    const selectAll = document.getElementById('select-all');
    const badgeCount = document.getElementById('badge-count');
    const payForm = document.getElementById('pay-form');
    const payBtn = document.getElementById('pay-btn');

    // Hidden inputs for selected items + their quantities
    function rebuildSelectedInputs() {
      [...payForm.querySelectorAll('input[name="selected_items[]"], input[name^="quantities["]')].forEach(e => e.remove());

      const checked = [...document.querySelectorAll('.row-check:checked')];
      checked.forEach(chk => {
        const tr = chk.closest('tr');
        const qty = parseInt(tr.querySelector('.qtyInput').value || '1', 10);

        const idInp = document.createElement('input');
        idInp.type = 'hidden';
        idInp.name = 'selected_items[]';
        idInp.value = chk.value;
        payForm.appendChild(idInp);

        const qInp = document.createElement('input');
        qInp.type = 'hidden';
        qInp.name = `quantities[${chk.value}]`;
        qInp.value = Math.max(1, isNaN(qty) ? 1 : qty);
        payForm.appendChild(qInp);
      });
      payBtn.disabled = checked.length === 0;
    }

    // Recompute selected block
    function recomputeSelected() {
      const rows = [...document.querySelectorAll('#cart-table tbody tr')];
      const checkedBoxes = [...document.querySelectorAll('.row-check')];
      let selectedTotal = 0;
      let selectedCount = 0;
      rows.forEach(tr => {
        const chk = tr.querySelector('.row-check');
        const qty = parseInt(tr.querySelector('.qtyInput').value || '1', 10);
        const price = parseFloat(tr.dataset.price || '0');
        if (chk && chk.checked) {
          selectedTotal += price * qty;
          selectedCount += qty;
        }
      });
      if (selectAll) {
        selectAll.checked = checkedBoxes.length > 0 && checkedBoxes.every(c => c.checked);
      }
      selectedCountEl.textContent = selectedCount;
      selectedTotalEl.textContent = fmtBDT(selectedTotal);
      rebuildSelectedInputs();
    }

    function recomputeSubtotalFromDOM() {
      const rows = [...document.querySelectorAll('#cart-table tbody tr')];
      let subtotal = 0;
      let count = 0;
      rows.forEach(tr => {
        const qty = parseInt(tr.querySelector('.qtyInput').value || '1', 10);
        const price = parseFloat(tr.dataset.price || '0');
        subtotal += price * qty;
        count += qty;
      });
      subtotalText.textContent = fmtBDT(subtotal);
      if (badgeCount) badgeCount.textContent = `${count} items`;
    }

    function liveLineTotal(tr) {
      const qty = parseInt(tr.querySelector('.qtyInput').value || '1', 10);
      const price = parseFloat(tr.dataset.price || '0');
      const line = tr.querySelector('.lineTotal');
      const val = qty * price;
      line.dataset.lineTotal = val.toFixed(2);
      line.textContent = fmtPlain(val);
    }

    function sendUpdate(tr) {
      const cartId = tr.getAttribute('data-cart-id');
      const qty = parseInt(tr.querySelector('.qtyInput').value || '1', 10);
      const csrf = "<?= htmlspecialchars($CSRF) ?>";

      fetch('cart.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: new URLSearchParams({
            ajax: '1',
            action: 'update',
            cart_id: cartId,
            quantity: qty,
            csrf_token: csrf
          }).toString()
        })
        .then(r => r.json())
        .then(d => {
          if (!d.ok) throw new Error(d.message || 'Update failed');
          tr.dataset.price = d.price;
          tr.querySelector('.qtyInput').value = d.quantity;
          const line = tr.querySelector('.lineTotal');
          line.dataset.lineTotal = d.line_total.toFixed(2);
          line.textContent = fmtPlain(d.line_total);
          subtotalText.textContent = fmtBDT(d.subtotal);
          if (badgeCount) badgeCount.textContent = `${d.item_count} items`;
          recomputeSelected();
          showToast('Updated');
        })
        .catch(err => showToast(err.message, true));
    }

    function sendRemove(tr) {
      const cartId = tr.getAttribute('data-cart-id');
      const csrf = "<?= htmlspecialchars($CSRF) ?>";
      fetch('cart.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: new URLSearchParams({
            ajax: '1',
            action: 'remove',
            cart_id: cartId,
            csrf_token: csrf
          }).toString()
        })
        .then(r => r.json())
        .then(d => {
          if (!d.ok) throw new Error(d.message || 'Remove failed');
          tr.remove();
          subtotalText.textContent = fmtBDT(d.subtotal);
          if (badgeCount) badgeCount.textContent = `${d.item_count} items`;
          recomputeSelected();
          if (document.querySelectorAll('#cart-table tbody tr').length === 0) {
            location.reload();
          } else {
            showToast('Removed');
          }
        })
        .catch(err => showToast(err.message, true));
    }

    function attachRowHandlers(tr) {
      const minus = tr.querySelector('.minus');
      const plus = tr.querySelector('.plus');
      const input = tr.querySelector('.qtyInput');
      const updateBtn = tr.querySelector('.btn-update');
      const removeBtn = tr.querySelector('.btn-remove');
      const chk = tr.querySelector('.row-check');

      minus?.addEventListener('click', () => {
        input.value = Math.max(1, (parseInt(input.value || '1', 10) - 1));
        liveLineTotal(tr);
        recomputeSelected();
        recomputeSubtotalFromDOM();
      });
      plus?.addEventListener('click', () => {
        input.value = (parseInt(input.value || '1', 10) + 1);
        liveLineTotal(tr);
        recomputeSelected();
        recomputeSubtotalFromDOM();
      });
      input?.addEventListener('change', () => {
        let v = parseInt(input.value || '1', 10);
        if (isNaN(v) || v < 1) v = 1;
        input.value = v;
        liveLineTotal(tr);
        recomputeSelected();
        recomputeSubtotalFromDOM();
      });
      updateBtn?.addEventListener('click', () => sendUpdate(tr));
      removeBtn?.addEventListener('click', () => {
        if (confirm('Remove this item?')) sendRemove(tr);
      });
      chk?.addEventListener('change', recomputeSelected);
    }

    document.querySelectorAll('#cart-table tbody tr').forEach(attachRowHandlers);

    // Select all
    if (selectAll) {
      selectAll.addEventListener('change', () => {
        document.querySelectorAll('.row-check').forEach(c => c.checked = selectAll.checked);
        recomputeSelected();
      });
    }

    // On submit: BULK UPDATE selected quantities to DB, then submit
    payForm?.addEventListener('submit', async (e) => {
      // build current selected IDs + quantities (also rebuilding hidden inputs)
      rebuildSelectedInputs();
      const selectedHidden = [...payForm.querySelectorAll('input[name="selected_items[]"]')];
      if (selectedHidden.length === 0) {
        e.preventDefault();
        return;
      }

      // Prepare quantities for selected rows
      const payload = new URLSearchParams();
      payload.append('ajax', '1');
      payload.append('action', 'bulk_update');
      payload.append('csrf_token', "<?= htmlspecialchars($CSRF) ?>");

      selectedHidden.forEach(inp => {
        const id = inp.value;
        const tr = document.querySelector(`#cart-table tbody tr[data-cart-id="${id}"]`);
        if (tr) {
          const qty = parseInt(tr.querySelector('.qtyInput').value || '1', 10);
          payload.append(`quantities[${id}]`, Math.max(1, isNaN(qty) ? 1 : qty));
        }
      });

      e.preventDefault();
      try {
        const res = await fetch('cart.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: payload.toString()
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message || 'Bulk update failed');
        // proceed to payment now that DB has authoritative quantities
        payForm.submit();
      } catch (err) {
        showToast(err.message || 'Bulk update error', true);
      }
    });

    // Initial draw
    if (selectAll) {
      const allRows = [...document.querySelectorAll('.row-check')];
      selectAll.checked = allRows.length > 0 && allRows.every(c => c.checked);
    }
    recomputeSelected();
  </script>
</body>

</html>
