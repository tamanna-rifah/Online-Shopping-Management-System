<?php
// admin_login.php - Admin Login Page
session_start();
require 'db.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT password FROM admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($db_password);
    $stmt->fetch();

    if ($stmt->num_rows > 0) {
        // If passwords are stored as plaintext in DB, compare directly; otherwise, use password_verify
        $isValid = ($password === $db_password) || password_verify($password, $db_password);
        if ($isValid) {
            $_SESSION['admin'] = true;
            header('Location: admin_dashboard.php');
            exit();
        }
    }

    $error_message = "Invalid username or password";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Login • Dress at Your Door</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merienda&display=swap" rel="stylesheet">
<style>
    :root {
        --bg1: #0f172a; --bg2: #1e293b; --brand: #38bdf8; --brand-strong: #0ea5e9;
        --card: rgba(255,255,255,0.08); --border: rgba(255,255,255,0.15);
        --text: #e5e7eb; --muted: #94a3b8; --danger: #f87171;
        --shadow: 0 10px 30px rgba(0,0,0,0.35), inset 0 1px 0 rgba(255,255,255,0.05);
    }
    * { box-sizing: border-box; }
    html, body {
        height: 100%; margin: 0;
        font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans';
        color: var(--text);
        background:
          radial-gradient(1200px 800px at 10% -20%, #0ea5e922, transparent 60%),
          radial-gradient(800px 600px at 100% 0%, #38bdf822, transparent 60%),
          linear-gradient(160deg, var(--bg1), var(--bg2));
    }

    /* ======= Header ======= */
    .header {
        padding: 25px 20px;
        background: linear-gradient(135deg, rgba(29,131,152,0.95), rgba(49,106,134,0.95));
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between; /* Title left, dropdown right */
        position: relative;
    }
    .header h1 {
        margin: 0;
        font-size: 28px;
        font-family: 'Merienda', cursive;
    }

    /* Dropdown (right side open) */
    .dropdown {
        position: relative;
        display: inline-block;
    }
    .dropbtn {
        background: none;
        border: none;
        color: white;
        font-size: 28px;
        cursor: pointer;
    }
    .dropdown-content {
        display: none;
        position: absolute;
        right: 0;  /* Always open to the right */
        top: 40px;
        background-color: white;
        min-width: 170px;
        border-radius: 8px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.25);
        z-index: 10;
        overflow: hidden;
    }
    .dropdown-content a {
        color: #333;
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        border-bottom: 1px solid #eee;
    }
    .dropdown-content a:hover {
        background-color: #007BFF;
        color: white;
    }
    .dropdown:hover .dropdown-content { display: block; }

    /* ======= Login Card ======= */
    .container { min-height: calc(100vh - 160px); display: grid; place-items: center; padding: 32px 16px; }
    .card {
        width: 100%; max-width: 420px; background: var(--card);
        border: 1px solid var(--border); border-radius: 18px; padding: 28px;
        backdrop-filter: blur(10px); box-shadow: var(--shadow);
    }
    .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
    .brand .logo {
        width: 42px; height: 42px; border-radius: 12px;
        background: linear-gradient(135deg, var(--brand), var(--brand-strong));
        display: grid; place-items: center; color: white; font-weight: 700; letter-spacing: .5px;
    }
    .brand h2 { font-size: 1.1rem; line-height: 1; margin: 0; color: #fff; }
    .subtitle { margin: 0 0 20px; color: var(--muted); font-size: .95rem; }
    form { display: grid; gap: 14px; }
    label { font-size: .9rem; color: #cbd5e1; margin-bottom: 4px; display: block; }
    .field { position: relative; }
    .input {
        width: 100%; padding: 12px 44px 12px 12px;
        background: rgba(255,255,255,0.06); border: 1px solid var(--border);
        border-radius: 10px; color: #fff; outline: none;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .input::placeholder { color: #94a3b8; }
    .input:focus { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(56,189,248,0.15); background: rgba(255,255,255,0.08); }
    .toggle {
        position: absolute; top: 50%; right: 10px; transform: translateY(-50%);
        background: none; border: none; color: #cbd5e1; cursor: pointer; font-size: .9rem;
        padding: 6px 8px; border-radius: 8px;
    }
    .toggle:hover { background: rgba(255,255,255,0.06); }
    .error {
        background: rgba(248,113,113,0.12); border: 1px solid rgba(248,113,113,0.35);
        color: #fecaca; padding: 10px 12px; border-radius: 10px; font-size: .9rem;
        margin-bottom: 8px;
    }
    .btn {
        margin-top: 4px; width: 100%; padding: 12px 14px; border: none; cursor: pointer;
        background: linear-gradient(135deg, var(--brand), var(--brand-strong));
        color: #06202a; font-weight: 700; border-radius: 10px;
        box-shadow: 0 6px 20px rgba(14,165,233,0.35);
        transition: transform .05s ease, filter .2s ease;
    }
    .btn:active { transform: translateY(1px); }

    /* ======= Footer ======= */
    footer {
        text-align: center;
        padding: 22px 16px;
        font-size: 0.9rem;
        color: black;
        background:rgba(30, 173, 189, 0.8);
    }
    .page-wrap { display: flex; flex-direction: column; min-height: 100vh; }
    .flex-fill { flex: 1 0 auto; }
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
            </div>
        </div>
    </div>

    <div class="page-wrap">
        <div class="flex-fill">
            <div class="container">
                <div class="card" role="region" aria-labelledby="loginTitle">
                    <div class="brand">
                        <div class="logo">DD</div>
                        <div>
                            <h2 id="loginTitle">Admin Sign In</h2>
                            <p class="subtitle">Dress at Your Door • Control Panel</p>
                        </div>
                    </div>

                    <form method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="yourtokenhere"/>

                        <div>
                            <label for="username">Username</label>
                            <input class="input" type="text" id="username" name="username"
                                autocomplete="username" placeholder="Enter your username" required />
                        </div>

                        <div class="field">
                            <label for="password">Password</label>
                            <input class="input" type="password" id="password" name="password"
                                autocomplete="current-password" placeholder="Enter your password" required />
                            <button class="toggle" type="button" aria-label="Show password" onclick="togglePw(event)">Show</button>
                        </div>

                        <button class="btn" type="submit">Sign In</button>
                    </form>
                </div>
            </div>
        </div>

        <footer>
            © <?= date('Y') ?> Dress at Your Door | All Rights Reserved
        </footer>
    </div>

<script>
function togglePw(e) {
    const pw = document.getElementById('password');
    const btn = e.currentTarget;
    if (pw.type === 'password') {
        pw.type = 'text';
        btn.textContent = 'Hide';
        btn.setAttribute('aria-label', 'Hide password');
    } else {
        pw.type = 'password';
        btn.textContent = 'Show';
        btn.setAttribute('aria-label', 'Show password');
    }
}
</script>
</body>
</html>
