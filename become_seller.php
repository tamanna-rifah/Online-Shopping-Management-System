<?php
require 'db.php';

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $shop_name = trim($_POST['shop_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (
        empty($name) ||
        empty($shop_name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($confirm_password)
    ) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {

        // Check if seller email already exists
        $check_query = $conn->prepare(
            "SELECT id FROM sellers WHERE email = ?"
        );
        $check_query->bind_param("s", $email);
        $check_query->execute();
        $check_query->store_result();

        if ($check_query->num_rows > 0) {
            $error = "A seller account with this email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $query = $conn->prepare("
                INSERT INTO sellers
                (name, shop_name, email, phone, password, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");

            $query->bind_param(
                "sssss",
                $name,
                $shop_name,
                $email,
                $phone,
                $hashed_password
            );

            if ($query->execute()) {
                $message = "Seller application submitted successfully! Your account is waiting for admin approval.";

                // Clear fields after successful registration
                $_POST = [];
            } else {
                $error = "Application failed. Please try again.";
            }

            $query->close();
        }

        $check_query->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Become a Seller • Dress at Your Door</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merienda&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg1: #0f172a;
            --bg2: #1e293b;
            --brand: #38bdf8;
            --brand-strong: #0ea5e9;
            --card: rgba(255, 255, 255, .08);
            --border: rgba(255, 255, 255, .15);
            --text: #e5e7eb;
            --muted: #94a3b8;
            --danger: #f87171;
            --shadow: 0 10px 30px rgba(0, 0, 0, .35),
                inset 0 1px 0 rgba(255, 255, 255, .05);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--text);

            background:
                radial-gradient(1200px 800px at 10% -20%,
                    #0ea5e922, transparent 60%),

                radial-gradient(800px 600px at 100% 0%,
                    #38bdf822, transparent 60%),

                linear-gradient(160deg, var(--bg1), var(--bg2));
        }

        /* Header */

        .header {
            padding: 25px 20px;

            background: linear-gradient(135deg,
                    rgba(29, 131, 152, .95),
                    rgba(49, 106, 134, .95));

            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-family: 'Merienda', cursive;
        }

        /* Dropdown */

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropbtn {
            background: none;
            border: none;
            color: #fff;
            font-size: 28px;
            cursor: pointer;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 40px;
            background: #fff;
            min-width: 190px;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, .25);
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
            background: #007BFF;
            color: #fff;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        /* Layout */

        .page-wrap {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .flex-fill {
            flex: 1 0 auto;
        }

        .container {
            min-height: calc(100vh - 160px);
            display: grid;
            place-items: center;
            padding: 32px 16px;
        }

        /* Card */

        .card {
            width: 100%;
            max-width: 540px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 28px;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;

            background: linear-gradient(135deg,
                    var(--brand),
                    var(--brand-strong));

            display: grid;
            place-items: center;
            color: #fff;
            font-weight: 700;
        }

        .brand h2 {
            font-size: 1.1rem;
            margin: 0;
            color: #fff;
        }

        .subtitle {
            margin: 4px 0 20px;
            color: var(--muted);
            font-size: .95rem;
        }

        /* Form */

        form {
            display: grid;
            gap: 14px;
        }

        label {
            font-size: .9rem;
            color: #cbd5e1;
            margin-bottom: 4px;
            display: block;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .field {
            position: relative;
        }

        .input {
            width: 100%;
            padding: 12px 44px 12px 12px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: #fff;
            outline: none;
        }

        .input::placeholder {
            color: #94a3b8;
        }

        .input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(56, 189, 248, .15);
        }

        .toggle {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #cbd5e1;
            cursor: pointer;
        }

        .hint {
            color: var(--muted);
            font-size: .85rem;
        }

        .error,
        .success {
            padding: 10px 12px;
            border-radius: 10px;
            font-size: .9rem;
            margin-bottom: 12px;
        }

        .error {
            background: rgba(248, 113, 113, .12);
            border: 1px solid rgba(248, 113, 113, .35);
            color: #fecaca;
        }

        .success {
            background: rgba(34, 197, 94, .12);
            border: 1px solid rgba(34, 197, 94, .35);
            color: #bbf7d0;
        }

        .btn {
            margin-top: 4px;
            width: 100%;
            padding: 12px 14px;
            border: none;
            cursor: pointer;

            background: linear-gradient(135deg,
                    var(--brand),
                    var(--brand-strong));

            color: #06202a;
            font-weight: 700;
            border-radius: 10px;

            box-shadow: 0 6px 20px rgba(14, 165, 233, .35);
        }

        .footer-note {
            text-align: center;
            margin-top: 6px;
            color: var(--muted);
            font-size: .9rem;
        }

        .footer-note a {
            color: #93c5fd;
            text-decoration: none;
        }

        footer {
            text-align: center;
            padding: 22px 16px;
            font-size: .9rem;
            color: black;
            background: rgba(30, 173, 189, .8);
        }

        @media(max-width:600px) {
            .row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <div class="header">

        <h1>Dress at Your Door!</h1>

        <div class="dropdown">

            <button class="dropbtn">☰</button>

            <div class="dropdown-content">

                <a href="index.php">Home</a>

                <a href="user_login.php">
                    User Login
                </a>

                <a href="seller_login.php">
                    Seller Login
                </a>

                <a href="admin_login.php">
                    Admin Login
                </a>

            </div>

        </div>

    </div>


    <div class="page-wrap">

        <div class="flex-fill">

            <div class="container">

                <div class="card">

                    <div class="brand">

                        <div class="logo">DD</div>

                        <div>

                            <h2>Become a Seller</h2>

                            <p class="subtitle">
                                Start selling your products with Dress at Your Door
                            </p>

                        </div>

                    </div>


                    <?php if (!empty($message)): ?>

                        <div class="success">
                            <?= htmlspecialchars($message) ?>
                        </div>

                    <?php endif; ?>


                    <?php if (!empty($error)): ?>

                        <div class="error">
                            <?= htmlspecialchars($error) ?>
                        </div>

                    <?php endif; ?>


                    <form method="POST">

                        <div class="field">

                            <label for="name">
                                Full Name
                            </label>

                            <input
                                class="input"
                                type="text"
                                id="name"
                                name="name"
                                placeholder="Your full name"
                                required
                                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

                        </div>


                        <div class="field">

                            <label for="shop_name">
                                Shop Name
                            </label>

                            <input
                                class="input"
                                type="text"
                                id="shop_name"
                                name="shop_name"
                                placeholder="Your shop name"
                                required
                                value="<?= htmlspecialchars($_POST['shop_name'] ?? '') ?>">

                        </div>


                        <div class="row">

                            <div class="field">

                                <label for="email">
                                    Email Address
                                </label>

                                <input
                                    class="input"
                                    type="email"
                                    id="email"
                                    name="email"
                                    placeholder="seller@example.com"
                                    required
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

                            </div>


                            <div class="field">

                                <label for="phone">
                                    Phone Number
                                </label>

                                <input
                                    class="input"
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    placeholder="01XXXXXXXXX"
                                    required
                                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

                            </div>

                        </div>


                        <div class="field">

                            <label for="password">
                                Password
                            </label>

                            <input
                                class="input"
                                type="password"
                                id="password"
                                name="password"
                                minlength="6"
                                placeholder="At least 6 characters"
                                required>

                            <button
                                class="toggle"
                                type="button"
                                onclick="togglePw(event,'password')">
                                Show
                            </button>

                            <p class="hint">
                                Use at least 6 characters.
                            </p>

                        </div>


                        <div class="field">

                            <label for="confirm_password">
                                Confirm Password
                            </label>

                            <input
                                class="input"
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                minlength="6"
                                placeholder="Re-enter password"
                                required>

                            <button
                                class="toggle"
                                type="button"
                                onclick="togglePw(event,'confirm_password')">
                                Show
                            </button>

                        </div>


                        <button class="btn" type="submit">
                            Submit Seller Application
                        </button>


                        <p class="footer-note">

                            Already applied?

                            <a href="login.php">
                                Seller Sign In
                            </a>

                        </p>

                    </form>

                </div>
            </div>
        </div>

        <footer>
            © <?= date('Y') ?>
            Dress at Your Door | All Rights Reserved
        </footer>

    </div>


    <script>
        function togglePw(e, id) {

            const pw = document.getElementById(id);
            const btn = e.currentTarget;

            if (pw.type === 'password') {

                pw.type = 'text';
                btn.textContent = 'Hide';

            } else {

                pw.type = 'password';
                btn.textContent = 'Show';

            }
        }
    </script>

</body>

</html>