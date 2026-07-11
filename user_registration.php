<?php
require 'db.php';

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $location = trim($_POST['location']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($location) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $check_query = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_query->bind_param("s", $email);
        $check_query->execute();
        $check_query->store_result();

        if ($check_query->num_rows > 0) {
            $error = "Email already exists. Please use a different email.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = $conn->prepare("INSERT INTO users (name, email, phone, location, password) VALUES (?, ?, ?, ?, ?)");
            $query->bind_param("sssss", $name, $email, $phone, $location, $hashed_password);

            if ($query->execute()) {
                $message = "Registration successful!";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $check_query->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>User Registration • Dress at Your Door</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merienda&display=swap" rel="stylesheet">
<style>
  :root {
    --bg1:#0f172a; --bg2:#1e293b; --brand:#38bdf8; --brand-strong:#0ea5e9;
    --card:rgba(255,255,255,.08); --border:rgba(255,255,255,.15);
    --text:#e5e7eb; --muted:#94a3b8; --danger:#f87171;
    --shadow:0 10px 30px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.05);
  }
  *{box-sizing:border-box}
  html,body{height:100%;margin:0;font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,'Helvetica Neue',Arial,'Noto Sans';color:var(--text);
    background:radial-gradient(1200px 800px at 10% -20%, #0ea5e922, transparent 60%),
               radial-gradient(800px 600px at 100% 0%, #38bdf822, transparent 60%),
               linear-gradient(160deg,var(--bg1),var(--bg2));}

  /* Header */
  .header{padding:25px 20px;background:linear-gradient(135deg,rgba(29,131,152,.95),rgba(49,106,134,.95));color:#fff;display:flex;align-items:center;justify-content:space-between}
  .header h1{margin:0;font-size:28px;font-family:'Merienda',cursive}
  .dropdown{position:relative;display:inline-block}
  .dropbtn{background:none;border:none;color:#fff;font-size:28px;cursor:pointer}
  .dropdown-content{display:none;position:absolute;right:0;top:40px;background:#fff;min-width:190px;border-radius:8px;box-shadow:0 8px 16px rgba(0,0,0,.25);z-index:10;overflow:hidden}
  .dropdown-content a{color:#333;padding:12px 16px;text-decoration:none;display:block;border-bottom:1px solid #eee}
  .dropdown-content a:hover{background:#007BFF;color:#fff}
  .dropdown:hover .dropdown-content{display:block}

  /* Layout */
  .page-wrap{display:flex;flex-direction:column;min-height:100vh}
  .flex-fill{flex:1 0 auto}
  .container{min-height:calc(100vh - 160px);display:grid;place-items:center;padding:32px 16px}

  /* Card */
  .card{width:100%;max-width:540px;background:var(--card);border:1px solid var(--border);border-radius:18px;padding:28px;backdrop-filter:blur(10px);box-shadow:var(--shadow)}
  .brand{display:flex;align-items:center;gap:12px;margin-bottom:18px}
  .logo{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,var(--brand),var(--brand-strong));display:grid;place-items:center;color:#fff;font-weight:700;letter-spacing:.5px}
  .brand h2{font-size:1.1rem;line-height:1;margin:0;color:#fff}
  .subtitle{margin:0 0 20px;color:var(--muted);font-size:.95rem}

  form{display:grid;gap:14px}
  label{font-size:.9rem;color:#cbd5e1;margin-bottom:4px;display:block}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .field{position:relative}
  .input{width:100%;padding:12px 44px 12px 12px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:10px;color:#fff;outline:none;transition:border-color .2s,box-shadow .2s,background .2s}
  .input::placeholder{color:#94a3b8}
  .input:focus{border-color:var(--brand);box-shadow:0 0 0 4px rgba(56,189,248,.15);background:rgba(255,255,255,.08)}

  .toggle{position:absolute;top:50%;right:10px;transform:translateY(-50%);background:none;border:none;color:#cbd5e1;cursor:pointer;font-size:.9rem;padding:6px 8px;border-radius:8px}
  .toggle:hover{background:rgba(255,255,255,.06)}

  .hint{color:var(--muted);font-size:.85rem}

  .error,.success{padding:10px 12px;border-radius:10px;font-size:.9rem;margin-bottom:8px}
  .error{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.35);color:#fecaca}
  .success{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);color:#bbf7d0}

  .btn{margin-top:4px;width:100%;padding:12px 14px;border:none;cursor:pointer;background:linear-gradient(135deg,var(--brand),var(--brand-strong));color:#06202a;font-weight:700;border-radius:10px;box-shadow:0 6px 20px rgba(14,165,233,.35);transition:transform .05s ease,filter .2s ease}
  .btn:active{transform:translateY(1px)}

  .footer-note{text-align:center;margin-top:6px;color:var(--muted);font-size:.9rem}
  footer{text-align:center;padding:22px 16px;font-size:.9rem;color:black;background:rgba(30, 173, 189, 0.8);}
</style>
</head>
<body>
  <div class="header">
    <h1>Dress at Your Door!</h1>
    <div class="dropdown">
      <button class="dropbtn" aria-label="Open menu">☰</button>
      <div class="dropdown-content" role="menu" aria-label="Main">
        <a href="index.php" role="menuitem">Home</a>
        <a href="user_login.php" role="menuitem">User Login</a>
        <a href="admin_login.php" role="menuitem">Admin Login</a>
      </div>
    </div>
  </div>

  <div class="page-wrap">
    <div class="flex-fill">
      <div class="container">
        <div class="card" role="region" aria-labelledby="registerTitle">
          <div class="brand">
            <div class="logo">DD</div>
            <div>
              <h2 id="registerTitle">Create Account</h2>
              <p class="subtitle">Join our shopping community</p>
            </div>
          </div>

          <?php if (!empty($message)): ?>
            <div class="success" role="status"><?php echo htmlspecialchars($message); ?></div>
          <?php endif; ?>

          <?php if (!empty($error)): ?>
            <div class="error" role="alert"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>

          <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? 'yourtokenhere') ?>" />

            <div class="field">
              <label for="name">Full Name</label>
              <input class="input" type="text" id="name" name="name" required placeholder="Jane Doe" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" />
            </div>

            <div class="field">
              <label for="email">Email Address</label>
              <input class="input" type="email" id="email" name="email" required placeholder="you@example.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" />
            </div>

            <div class="row">
              <div class="field">
                <label for="phone">Phone Number</label>
                <input class="input" type="tel" id="phone" name="phone" required placeholder="01XXXXXXXXX" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" />
              </div>
              <div class="field">
                <label for="location">Location</label>
                <input class="input" type="text" id="location" name="location" required placeholder="City, Area" value="<?= isset($_POST['location']) ? htmlspecialchars($_POST['location']) : '' ?>" />
              </div>
            </div>

            <div class="field">
              <label for="password">Password</label>
              <input class="input" type="password" id="password" name="password" required minlength="6" placeholder="At least 6 characters" />
              <button class="toggle" type="button" aria-label="Show password" onclick="togglePw(event,'password')">Show</button>
              <p class="hint">Use at least 6 characters. Mix letters & numbers for better security.</p>
            </div>

            <div class="field">
              <label for="confirm_password">Confirm Password</label>
              <input class="input" type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Re-enter password" />
              <button class="toggle" type="button" aria-label="Show password" onclick="togglePw(event,'confirm_password')">Show</button>
            </div>

            <button class="btn" type="submit">Create Account</button>
            <p class="footer-note">Already have an account? <a href="user_login.php" style="color:#93c5fd;text-decoration:none">Sign in</a></p>
          </form>
        </div>
      </div>
    </div>

    <footer>
      © <?= date('Y') ?> Dress at Your Door | All Rights Reserved
    </footer>
  </div>

<script>
function togglePw(e, id){
  const pw=document.getElementById(id); const btn=e.currentTarget;
  if(pw.type==='password'){ pw.type='text'; btn.textContent='Hide'; btn.setAttribute('aria-label','Hide password'); }
  else { pw.type='password'; btn.textContent='Show'; btn.setAttribute('aria-label','Show password'); }
}
// Optional: client-side check to help UX (server-side should still validate!)
(function(){
  const form=document.querySelector('form');
  const pass=document.getElementById('password');
  const cpass=document.getElementById('confirm_password');
  form.addEventListener('submit',function(ev){
    if(pass.value!==cpass.value){
      ev.preventDefault();
      alert('Passwords do not match.');
      cpass.focus();
    }
  });
})();
</script>
</body>
</html>