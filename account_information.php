<?php
// account_information.php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
start_auth_session();
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

// Handle update (name/phone) + optional password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        $flash = ['type' => 'error', 'msg' => 'Invalid request. Please try again.'];
    } else {
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Basic validations
        if ($name === '') {
            $flash = ['type' => 'error', 'msg' => 'Name is required.'];
        } else {
            // Update name, phone
            $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE email = ?");
            $stmt->bind_param('sss', $name, $phone, $user_email);
            $ok = $stmt->execute();
            $stmt->close();

            // Optional password change (only if all provided)
            $pwChanged = false;
            $current_pw = $_POST['current_password'] ?? '';
            $new_pw     = $_POST['new_password'] ?? '';
            $confirm_pw = $_POST['confirm_password'] ?? '';

            if ($current_pw !== '' || $new_pw !== '' || $confirm_pw !== '') {
                if ($new_pw === '' || $confirm_pw === '') {
                    $flash = ['type' => 'error', 'msg' => 'Please fill new password and confirm password.'];
                } elseif ($new_pw !== $confirm_pw) {
                    $flash = ['type' => 'error', 'msg' => 'New password and confirm password do not match.'];
                } else {
                    // Verify current password
                    $stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
                    $stmt->bind_param('s', $user_email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();

                    if (!$user || !password_verify($current_pw, $user['password'])) {
                        $flash = ['type' => 'error', 'msg' => 'Current password is incorrect.'];
                    } else {
                        $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                        $stmt->bind_param('ss', $hash, $user_email);
                        $pwChanged = $stmt->execute();
                        $stmt->close();
                    }
                }
            }

            if (!$flash['type']) {
                if ($ok && ($pwChanged || ($current_pw === '' && $new_pw === '' && $confirm_pw === ''))) {
                    $flash = ['type' => 'success', 'msg' => 'Account updated successfully.'];
                } else {
                    $flash = ['type' => 'error', 'msg' => 'Could not update account. Please try again.'];
                }
            }
        }
    }
}

// Load current user info
$stmt = $conn->prepare("SELECT email, name, phone FROM users WHERE email = ?");
$stmt->bind_param('s', $user_email);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

$name  = htmlspecialchars($profile['name']  ?? '', ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($profile['email'] ?? $user_email, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Information</title>
<style>
  body{font-family:Arial, sans-serif; margin:0; background:#f7f8fb; color:#222;}
  .wrap{max-width:900px; margin:30px auto; padding:0 16px;}
  .card{background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:18px 16px; box-shadow:0 6px 20px rgba(0,0,0,.05);}
  h1{margin:0 0 14px; font-size:22px}
  .grid{display:grid; grid-template-columns:1fr 1fr; gap:14px}
  @media (max-width:700px){.grid{grid-template-columns:1fr}}
  label{display:block; font-weight:700; margin-bottom:6px}
  input{width:100%; padding:10px; border-radius:8px; border:1px solid #d1d5db; outline:none}
  .muted{color:#6b7280; font-size:.92rem}
  .row{margin-bottom:12px}
  .btn{display:inline-block; background:#0d6efd; color:#fff; border:none; border-radius:10px; padding:10px 16px; cursor:pointer; font-weight:700}
  .btn.secondary{background:#6c757d}
  .toolbar{display:flex; gap:10px; margin-top:10px}
  .flash{padding:10px 12px; border-radius:10px; margin-bottom:12px; font-weight:700}
  .flash.success{background:#e8fff3; color:#106a3a; border:1px solid #b6f0ce}
  .flash.error{background:#fff1f2; color:#9f1239; border:1px solid #fecdd3}
  .section-title{font-weight:800; margin:8px 0 6px}
  a.link{color:#0d6efd; text-decoration:none}
  a.link:hover{text-decoration:underline}
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Account Information</h1>
      <p class="muted">Logged in as <strong><?= $email ?></strong> · <a class="link" href="user_dashboard.php">Back to Home</a></p>

      <?php if ($flash['type']): ?>
        <div class="flash <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
      <?php endif; ?>

      <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="grid">
          <div class="row">
            <label for="name">Full Name</label>
            <input id="name" name="name" type="text" value="<?= $name ?>" required>
          </div>
          <div class="row">
            <label for="phone">Phone</label>
            <input id="phone" name="phone" type="text" value="<?= $phone ?>">
          </div>
        </div>

        <div class="section-title">Change Password (optional)</div>
        <div class="grid">
          <div class="row">
            <label for="current_password">Current Password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password">
          </div>
          <div class="row">
            <label for="new_password">New Password</label>
            <input id="new_password" name="new_password" type="password" autocomplete="new-password">
          </div>
          <div class="row">
            <label for="confirm_password">Confirm New Password</label>
            <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password">
          </div>
        </div>

        <div class="toolbar">
          <button class="btn" type="submit">Save Changes</button>
          <a class="btn secondary" href="user_dashboard.php">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
