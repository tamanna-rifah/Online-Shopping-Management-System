<?php
// address_book.php
declare(strict_types=1);
session_start();
require 'db.php';

// Require login
if (!isset($_SESSION['user_email'])) {
  header('Location: user_login.php');
  exit();
}

$user_email = $_SESSION['user_email'];

// CSRF setup
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

$flash = ['type' => null, 'msg' => null];

function clean_str(?string $s): string
{
  return trim((string)$s);
}

// Action resolver
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $flash = ['type' => 'error', 'msg' => 'Invalid request. Please try again.'];
  } else {
    if ($action === 'save') {
      $id       = isset($_POST['id']) ? (int)$_POST['id'] : 0; // 0 = new
      $name     = clean_str($_POST['name'] ?? '');
      $phone    = clean_str($_POST['phone'] ?? '');
      $line1    = clean_str($_POST['line1'] ?? '');
      $line2    = clean_str($_POST['line2'] ?? '');
      $city     = clean_str($_POST['city'] ?? '');
      $state    = clean_str($_POST['state'] ?? '');
      $postal   = clean_str($_POST['postal_code'] ?? '');
      $country  = clean_str($_POST['country'] ?? 'Bangladesh');
      $is_default = isset($_POST['is_default']) ? 1 : 0;

      if ($name === '' || $line1 === '' || $city === '') {
        $flash = ['type' => 'error', 'msg' => 'Name, Address Line 1 and City are required.'];
      } else {
        if ($id > 0) {
          // Update only if the address belongs to the user
          $stmt = $conn->prepare(
            "UPDATE addresses
                         SET name=?, phone=?, line1=?, line2=?, city=?, state=?, postal_code=?, country=?, is_default=?
                         WHERE id=? AND user_email=?"
          );
          $stmt->bind_param(
            'ssssssssisi',
            $name,
            $phone,
            $line1,
            $line2,
            $city,
            $state,
            $postal,
            $country,
            $is_default,
            $id,
            $user_email
          );
          $ok = $stmt->execute();
          $stmt->close();
        } else {
          $stmt = $conn->prepare(
            "INSERT INTO addresses
                         (user_email, name, phone, line1, line2, city, state, postal_code, country, is_default)
                         VALUES (?,?,?,?,?,?,?,?,?,?)"
          );
          $stmt->bind_param(
            'sssssssssi',
            $user_email,
            $name,
            $phone,
            $line1,
            $line2,
            $city,
            $state,
            $postal,
            $country,
            $is_default
          );
          $ok = $stmt->execute();
          $id = $ok ? (int)$conn->insert_id : 0;
          $stmt->close();
        }

        if ($ok && $is_default) {
          // Ensure only one default per user
          $stmt = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_email = ? AND id <> ?");
          $stmt->bind_param('si', $user_email, $id);
          $stmt->execute();
          $stmt->close();
        }

        $flash = $ok
          ? ['type' => 'success', 'msg' => 'Address saved successfully.']
          : ['type' => 'error', 'msg' => 'Failed to save address.'];
      }
    } elseif ($action === 'delete') {
      $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM addresses WHERE id = ? AND user_email = ?");
        $stmt->bind_param('is', $id, $user_email);
        $ok = $stmt->execute();
        $stmt->close();
        $flash = $ok
          ? ['type' => 'success', 'msg' => 'Address deleted.']
          : ['type' => 'error', 'msg' => 'Could not delete address.'];
      }
    } elseif ($action === 'make_default') {
      $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      if ($id > 0) {
        // Set requested one to default (only if it belongs to user)
        $stmt = $conn->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_email = ?");
        $stmt->bind_param('is', $id, $user_email);
        $ok1 = $stmt->execute();
        $stmt->close();

        if ($ok1) {
          $stmt = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_email = ? AND id <> ?");
          $stmt->bind_param('si', $user_email, $id);
          $stmt->execute();
          $stmt->close();
          $flash = ['type' => 'success', 'msg' => 'Default address updated.'];
        } else {
          $flash = ['type' => 'error', 'msg' => 'Could not set default address.'];
        }
      }
    }
  }
}

// For edit form prefill
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit = null;
if ($edit_id > 0) {
  $stmt = $conn->prepare("SELECT * FROM addresses WHERE id = ? AND user_email = ?");
  $stmt->bind_param('is', $edit_id, $user_email);
  $stmt->execute();
  $edit = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// Load list
$stmt = $conn->prepare("SELECT * FROM addresses WHERE user_email = ? ORDER BY is_default DESC, created_at DESC");
$stmt->bind_param('s', $user_email);
$stmt->execute();
$list = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Address Book</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background: #f7f8fb;
      color: #222;
    }

    .wrap {
      max-width: 1000px;
      margin: 30px auto;
      padding: 0 16px;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px
    }

    @media (max-width:900px) {
      .grid {
        grid-template-columns: 1fr
      }
    }

    .card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 18px 16px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, .05);
    }

    h1 {
      margin: 0 0 12px;
      font-size: 22px
    }

    h2 {
      margin: 0 0 10px;
      font-size: 18px
    }

    .row {
      margin-bottom: 12px
    }

    label {
      display: block;
      font-weight: 700;
      margin-bottom: 6px
    }

    input {
      width: 100%;
      padding: 10px;
      border-radius: 8px;
      border: 1px solid #d1d5db;
      outline: none
    }

    .muted {
      color: #6b7280;
      font-size: .92rem
    }

    .btn {
      display: inline-block;
      background: #0d6efd;
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 10px 14px;
      cursor: pointer;
      font-weight: 700;
      text-decoration: none
    }

    .btn.gray {
      background: #6c757d
    }

    .btn.red {
      background: #dc3545
    }

    .btn.outline {
      background: #fff;
      color: #0d6efd;
      border: 1px solid #0d6efd
    }

    .toolbar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap
    }

    .list {
      display: grid;
      gap: 12px
    }

    .addr {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 12px
    }

    .flag {
      display: inline-block;
      background: #eef6ff;
      color: #0d6efd;
      border: 1px solid #cfe1ff;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: .8rem;
      font-weight: 800;
      margin-left: 8px
    }

    .flash {
      padding: 10px 12px;
      border-radius: 10px;
      margin-bottom: 12px;
      font-weight: 700
    }

    .flash.success {
      background: #e8fff3;
      color: #106a3a;
      border: 1px solid #b6f0ce
    }

    .flash.error {
      background: #fff1f2;
      color: #9f1239;
      border: 1px solid #fecdd3
    }

    a.link {
      color: #0d6efd;
      text-decoration: none
    }

    a.link:hover {
      text-decoration: underline
    }
  </style>
</head>

<body>
  <div class="wrap">
    <h1>Address Book</h1>
    <p class="muted"><a class="link" href="user_dashboard.php">Back to Home</a></p>

    <?php if ($flash['type']): ?>
      <div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="grid">
      <!-- Form -->
      <div class="card">
        <h2><?= $edit ? 'Edit Address' : 'Add New Address' ?></h2>
        <form method="post" action="">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

          <div class="row">
            <label for="name">Full Name *</label>
            <input id="name" name="name" type="text" value="<?= htmlspecialchars($edit['name'] ?? '', ENT_QUOTES) ?>" required>
          </div>
          <div class="row">
            <label for="phone">Phone</label>
            <input id="phone" name="phone" type="text" value="<?= htmlspecialchars($edit['phone'] ?? '', ENT_QUOTES) ?>">
          </div>
          <div class="row">
            <label for="line1">Address Line 1 *</label>
            <input id="line1" name="line1" type="text" value="<?= htmlspecialchars($edit['line1'] ?? '', ENT_QUOTES) ?>" required>
          </div>
          <div class="row">
            <label for="line2">Address Line 2</label>
            <input id="line2" name="line2" type="text" value="<?= htmlspecialchars($edit['line2'] ?? '', ENT_QUOTES) ?>">
          </div>

          <div class="grid" style="grid-template-columns:1fr 1fr; gap:12px">
            <div class="row">
              <label for="city">City *</label>
              <input id="city" name="city" type="text" value="<?= htmlspecialchars($edit['city'] ?? '', ENT_QUOTES) ?>" required>
            </div>
            <div class="row">
              <label for="state">State / Division</label>
              <input id="state" name="state" type="text" value="<?= htmlspecialchars($edit['state'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="row">
              <label for="postal_code">Postal Code</label>
              <input id="postal_code" name="postal_code" type="text" value="<?= htmlspecialchars($edit['postal_code'] ?? '', ENT_QUOTES) ?>">
            </div>
            <div class="row">
              <label for="country">Country</label>
              <input id="country" name="country" type="text" value="<?= htmlspecialchars($edit['country'] ?? 'Bangladesh', ENT_QUOTES) ?>">
            </div>
          </div>

          <div class="row" style="display:flex;align-items:center;gap:8px">
            <input id="is_default" name="is_default" type="checkbox" <?= !empty($edit['is_default']) ? 'checked' : '' ?>>
            <label for="is_default" style="margin:0;font-weight:600">Set as default</label>
          </div>

          <div class="toolbar">
            <button class="btn" type="submit">Save Address</button>
            <?php if ($edit): ?>
              <a class="btn gray" href="address_book.php">Cancel Edit</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- List -->
      <div class="card">
        <h2>Saved Addresses</h2>
        <div class="list">
          <?php if ($list && $list->num_rows > 0): ?>
            <?php while ($row = $list->fetch_assoc()): ?>
              <div class="addr">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                  <div>
                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                    <?php if ((int)$row['is_default'] === 1): ?><span class="flag">Default</span><?php endif; ?><br>
                    <span class="muted"><?= htmlspecialchars((string)($row['phone'] ?? '')) ?></span>
                  </div>
                  <div class="toolbar">
                    <form method="post" action="" onsubmit="return confirm('Delete this address?')">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    </form>
                    <?php if ((int)$row['is_default'] !== 1): ?>
                      <form method="post" action="">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="make_default">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <button class="btn" type="submit">Make Default</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
                <div style="margin-top:8px">
                  <?= htmlspecialchars($row['line1']) ?><?= !empty($row['line2']) ? ', ' . htmlspecialchars($row['line2']) : '' ?><br>
                  <?= htmlspecialchars($row['city']) ?><?= !empty($row['state']) ? ', ' . htmlspecialchars($row['state']) : '' ?><?= !empty($row['postal_code']) ? ' - ' . htmlspecialchars($row['postal_code']) : '' ?><br>
                  <?= htmlspecialchars($row['country'] ?: 'Bangladesh') ?>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <p class="muted">No addresses saved yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</body>

</html>