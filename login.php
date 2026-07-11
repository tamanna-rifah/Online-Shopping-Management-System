<?php
// login.php — Unified Login for User, Seller and Admin

declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';

$error_message = '';

/* =========================================
   CSRF TOKEN
========================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* =========================================
   LOGIN PROCESS
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedCsrf = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $postedCsrf)
    ) {
        $error_message = 'Invalid request. Please refresh and try again.';
    } else {

        $role = strtolower(trim($_POST['role'] ?? 'user'));
        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($identity === '' || $password === '') {

            $error_message = 'Please enter your login information and password.';
        } elseif (!in_array($role, ['user', 'seller', 'admin'], true)) {

            $error_message = 'Please select a valid account type.';
        } else {

            /* =====================================
               USER LOGIN
            ===================================== */

            if ($role === 'user') {

                $stmt = $conn->prepare("
                    SELECT email, password
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");

                $stmt->bind_param('s', $identity);
                $stmt->execute();

                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                $stmt->close();

                if (
                    $user &&
                    password_verify(
                        $password,
                        (string) $user['password']
                    )
                ) {

                    /* Clear other account sessions */
                    unset(
                        $_SESSION['admin'],
                        $_SESSION['admin_name'],
                        $_SESSION['seller_id'],
                        $_SESSION['seller_name'],
                        $_SESSION['seller_shop_name'],
                        $_SESSION['seller_email']
                    );

                    $_SESSION['user_email'] =
                        (string) $user['email'];

                    /* Keep your existing cart redirect system */
                    if (!empty($_SESSION['redirect_to_cart'])) {

                        unset($_SESSION['redirect_to_cart']);

                        $productId =
                            $_SESSION['product_id'] ?? null;

                        unset($_SESSION['product_id']);

                        if ($productId !== null) {

                            header(
                                'Location: add_to_cart.php?product_id=' .
                                    urlencode((string) $productId)
                            );
                        } else {

                            header('Location: user_dashboard.php');
                        }
                    } else {

                        header('Location: user_dashboard.php');
                    }

                    exit();
                } else {

                    $error_message =
                        'Invalid user email or password.';
                }
            }


            /* =====================================
               SELLER LOGIN
            ===================================== */ elseif ($role === 'seller') {

                $stmt = $conn->prepare("
                    SELECT
                        id,
                        name,
                        shop_name,
                        email,
                        password,
                        status
                    FROM sellers
                    WHERE email = ?
                    LIMIT 1
                ");

                $stmt->bind_param('s', $identity);
                $stmt->execute();

                $result = $stmt->get_result();
                $seller = $result->fetch_assoc();

                $stmt->close();

                if (
                    !$seller ||
                    !password_verify(
                        $password,
                        (string) $seller['password']
                    )
                ) {

                    $error_message =
                        'Invalid seller email or password.';
                } else {

                    $status = strtolower(
                        trim((string) $seller['status'])
                    );

                    if ($status === 'pending') {

                        $error_message =
                            'Your seller application is still waiting for admin approval.';
                    } elseif ($status !== 'approved') {

                        $error_message =
                            'Your seller account is blocked or unavailable.';
                    } else {

                        /* Clear other account sessions */
                        unset(
                            $_SESSION['admin'],
                            $_SESSION['admin_name'],
                            $_SESSION['user_email']
                        );

                        session_regenerate_id(true);

                        $_SESSION['seller_id'] =
                            (int) $seller['id'];

                        $_SESSION['seller_name'] =
                            (string) $seller['name'];

                        $_SESSION['seller_shop_name'] =
                            (string) $seller['shop_name'];

                        $_SESSION['seller_email'] =
                            (string) $seller['email'];

                        header('Location: seller_dashboard.php');
                        exit();
                    }
                }
            }


            /* =====================================
               ADMIN LOGIN
            ===================================== */ elseif ($role === 'admin') {

                $stmt = $conn->prepare("
                    SELECT username, password
                    FROM admins
                    WHERE username = ?
                    LIMIT 1
                ");

                $stmt->bind_param('s', $identity);
                $stmt->execute();

                $result = $stmt->get_result();
                $admin = $result->fetch_assoc();

                $stmt->close();

                if ($admin) {

                    $dbPassword =
                        (string) $admin['password'];

                    /*
                       Supports your current database:
                       - plaintext admin password
                       - hashed admin password
                    */
                    $isValid =
                        hash_equals($dbPassword, $password) ||
                        password_verify($password, $dbPassword);

                    if ($isValid) {

                        /* Clear other account sessions */
                        unset(
                            $_SESSION['user_email'],
                            $_SESSION['seller_id'],
                            $_SESSION['seller_name'],
                            $_SESSION['seller_shop_name'],
                            $_SESSION['seller_email']
                        );

                        session_regenerate_id(true);

                        $_SESSION['admin'] = true;

                        $_SESSION['admin_name'] =
                            (string) $admin['username'];

                        header('Location: admin_dashboard.php');
                        exit();
                    }
                }

                $error_message =
                    'Invalid admin username or password.';
            }
        }
    }
}

/* Keep selected role and entered identity after an error */

$selectedRole = $_POST['role'] ?? 'user';
$enteredIdentity = $_POST['identity'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Login • Dress at Your Door</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merienda&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --bg1: #0f172a;
            --bg2: #1e293b;
            --brand: #38bdf8;
            --brand-strong: #0ea5e9;
            --card: rgba(255, 255, 255, 0.08);
            --border: rgba(255, 255, 255, 0.15);
            --text: #e5e7eb;
            --muted: #94a3b8;
            --danger: #f87171;
            --shadow:
                0 10px 30px rgba(0, 0, 0, 0.35),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;

            font-family:
                'Inter',
                system-ui,
                -apple-system,
                Segoe UI,
                Roboto,
                Arial;

            color: var(--text);

            background:
                radial-gradient(1200px 800px at 10% -20%,
                    #0ea5e922,
                    transparent 60%),
                radial-gradient(800px 600px at 100% 0%,
                    #38bdf822,
                    transparent 60%),
                linear-gradient(160deg,
                    var(--bg1),
                    var(--bg2));
        }


        /* =========================================
       HEADER
    ========================================= */

        .header {
            padding: 25px 20px;

            background:
                linear-gradient(135deg,
                    rgba(29, 131, 152, 0.95),
                    rgba(49, 106, 134, 0.95));

            color: white;

            display: flex;
            align-items: center;
            justify-content: space-between;

            position: relative;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-family: 'Merienda', cursive;
        }


        /* =========================================
       DROPDOWN
    ========================================= */

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

            right: 0;
            top: 40px;

            background: white;

            min-width: 190px;

            border-radius: 8px;

            box-shadow:
                0 8px 16px rgba(0, 0, 0, 0.25);

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
            color: white;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }


        /* =========================================
       PAGE
    ========================================= */

        .page-wrap {
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 88px);
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


        /* =========================================
       LOGIN CARD
    ========================================= */

        .card {
            width: 100%;
            max-width: 460px;

            background: var(--card);

            border:
                1px solid var(--border);

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

        .brand .logo {
            width: 42px;
            height: 42px;

            border-radius: 12px;

            background:
                linear-gradient(135deg,
                    var(--brand),
                    var(--brand-strong));

            display: grid;
            place-items: center;

            color: white;

            font-weight: 700;
        }

        .brand h2 {
            font-size: 1.1rem;
            margin: 0;
            color: white;
        }

        .subtitle {
            margin: 4px 0 0;

            color: var(--muted);

            font-size: .95rem;
        }


        /* =========================================
       ROLE SELECTOR
    ========================================= */

        .role-title {
            font-size: .9rem;

            color: #cbd5e1;

            margin-bottom: 8px;
        }

        .role-selector {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 8px;

            margin-bottom: 18px;
        }

        .role-selector input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .role-selector label {
            text-align: center;

            padding: 10px 6px;

            border:
                1px solid var(--border);

            border-radius: 10px;

            color: #cbd5e1;

            cursor: pointer;

            font-size: .88rem;

            font-weight: 600;

            background:
                rgba(255, 255, 255, .04);

            transition:
                .2s ease;
        }

        .role-selector label:hover {
            background:
                rgba(56, 189, 248, .1);
        }

        .role-selector input:checked+label {
            color: white;

            border-color:
                var(--brand);

            background:
                linear-gradient(135deg,
                    rgba(56, 189, 248, .25),
                    rgba(14, 165, 233, .25));

            box-shadow:
                0 0 0 3px rgba(56, 189, 248, .1);
        }


        /* =========================================
       FORM
    ========================================= */

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

        .field {
            position: relative;
        }

        .input {
            width: 100%;

            padding:
                12px 44px 12px 12px;

            background:
                rgba(255, 255, 255, 0.06);

            border:
                1px solid var(--border);

            border-radius: 10px;

            color: white;

            outline: none;

            transition:
                border-color .2s,
                box-shadow .2s,
                background .2s;
        }

        .input::placeholder {
            color: #94a3b8;
        }

        .input:focus {
            border-color: var(--brand);

            box-shadow:
                0 0 0 4px rgba(56, 189, 248, 0.15);

            background:
                rgba(255, 255, 255, 0.08);
        }

        .toggle {
            position: absolute;

            top: 50%;
            right: 10px;

            transform:
                translateY(-50%);

            background: none;

            border: none;

            color: #cbd5e1;

            cursor: pointer;

            font-size: .9rem;

            padding: 6px 8px;

            border-radius: 8px;
        }

        .toggle:hover {
            background:
                rgba(255, 255, 255, 0.06);
        }


        /* =========================================
       ERROR
    ========================================= */

        .error {
            background:
                rgba(248, 113, 113, 0.12);

            border:
                1px solid rgba(248, 113, 113, 0.35);

            color: #fecaca;

            padding:
                10px 12px;

            border-radius:
                10px;

            font-size:
                .9rem;

            margin-bottom:
                14px;
        }


        /* =========================================
       BUTTON
    ========================================= */

        .btn {
            margin-top: 4px;

            width: 100%;

            padding:
                12px 14px;

            border: none;

            cursor: pointer;

            background:
                linear-gradient(135deg,
                    var(--brand),
                    var(--brand-strong));

            color: #06202a;

            font-weight: 700;

            border-radius: 10px;

            box-shadow:
                0 6px 20px rgba(14, 165, 233, 0.35);
        }

        .btn:active {
            transform:
                translateY(1px);
        }


        /* =========================================
       EXTRA LINKS
    ========================================= */

        .extra-links {
            margin-top: 15px;

            text-align: center;

            color: var(--muted);

            font-size: .9rem;
        }

        .extra-links a {
            color: #93c5fd;

            text-decoration: none;
        }

        .extra-links a:hover {
            text-decoration: underline;
        }


        /* =========================================
       FOOTER
    ========================================= */

        footer {
            text-align: center;

            padding:
                22px 16px;

            font-size:
                .9rem;

            color: black;

            background:
                rgba(30, 173, 189, .8);
        }


        @media (max-width: 480px) {

            .role-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>

</head>


<body>


    <div class="header">

        <h1>
            Dress at Your Door!
        </h1>


        <div class="dropdown">

            <button
                class="dropbtn"
                aria-label="Open menu">
                ☰
            </button>


            <div class="dropdown-content">

                <a href="index.php">
                    Home
                </a>

                <a href="user_registration.php">
                    Register
                </a>

                <a href="become_seller.php">
                    Become a Seller
                </a>

            </div>

        </div>

    </div>



    <div class="page-wrap">

        <div class="flex-fill">

            <div class="container">


                <div
                    class="card"
                    role="region"
                    aria-labelledby="loginTitle">


                    <div class="brand">

                        <div class="logo">
                            DD
                        </div>


                        <div>

                            <h2 id="loginTitle">
                                Sign In
                            </h2>

                            <p
                                class="subtitle"
                                id="portalSubtitle">
                                Dress at Your Door • Customer Portal
                            </p>

                        </div>

                    </div>


                    <?php if ($error_message !== ''): ?>

                        <div
                            class="error"
                            role="alert">
                            <?= htmlspecialchars(
                                $error_message,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                    <?php endif; ?>



                    <form
                        method="POST"
                        novalidate>


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                        $_SESSION['csrf_token'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>">


                        <!-- ROLE -->

                        <div>

                            <div class="role-title">
                                Sign in as
                            </div>


                            <div class="role-selector">


                                <input
                                    type="radio"
                                    id="roleUser"
                                    name="role"
                                    value="user"

                                    <?= $selectedRole === 'user'
                                        ? 'checked'
                                        : ''
                                    ?>>

                                <label for="roleUser">
                                    User
                                </label>



                                <input
                                    type="radio"
                                    id="roleSeller"
                                    name="role"
                                    value="seller"

                                    <?= $selectedRole === 'seller'
                                        ? 'checked'
                                        : ''
                                    ?>>

                                <label for="roleSeller">
                                    Seller
                                </label>



                                <input
                                    type="radio"
                                    id="roleAdmin"
                                    name="role"
                                    value="admin"

                                    <?= $selectedRole === 'admin'
                                        ? 'checked'
                                        : ''
                                    ?>>

                                <label for="roleAdmin">
                                    Admin
                                </label>


                            </div>

                        </div>



                        <!-- IDENTITY -->

                        <div>

                            <label
                                for="identity"
                                id="identityLabel">
                                Email
                            </label>


                            <input
                                class="input"
                                type="text"
                                id="identity"
                                name="identity"

                                value="<?= htmlspecialchars(
                                            $enteredIdentity,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"

                                placeholder="Enter your email"

                                autocomplete="username"

                                required>

                        </div>



                        <!-- PASSWORD -->

                        <div class="field">

                            <label for="password">
                                Password
                            </label>


                            <input
                                class="input"
                                type="password"
                                id="password"
                                name="password"

                                autocomplete="current-password"

                                placeholder="Enter your password"

                                required>


                            <button
                                class="toggle"
                                type="button"
                                aria-label="Show password"
                                onclick="togglePw(event)">
                                Show
                            </button>

                        </div>



                        <button
                            class="btn"
                            type="submit">
                            Sign In
                        </button>


                        <div
                            class="extra-links"
                            id="extraLinks">

                            New here?

                            <a href="user_registration.php">
                                Create an account
                            </a>

                        </div>


                    </form>


                </div>

            </div>

        </div>


        <footer>

            © <?= date('Y') ?>

            Dress at Your Door

            | All Rights Reserved

        </footer>


    </div>



    <script>
        /* =========================================
   PASSWORD SHOW / HIDE
========================================= */

        function togglePw(event) {

            const password =
                document.getElementById('password');

            const button =
                event.currentTarget;


            if (password.type === 'password') {

                password.type = 'text';

                button.textContent = 'Hide';

                button.setAttribute(
                    'aria-label',
                    'Hide password'
                );

            } else {

                password.type = 'password';

                button.textContent = 'Show';

                button.setAttribute(
                    'aria-label',
                    'Show password'
                );
            }
        }


        /* =========================================
           CHANGE FORM TEXT BASED ON ROLE
        ========================================= */

        const roleInputs =
            document.querySelectorAll(
                'input[name="role"]'
            );

        const identityLabel =
            document.getElementById(
                'identityLabel'
            );

        const identityInput =
            document.getElementById(
                'identity'
            );

        const portalSubtitle =
            document.getElementById(
                'portalSubtitle'
            );

        const extraLinks =
            document.getElementById(
                'extraLinks'
            );


        function updateRoleUI() {

            const selectedRole =
                document.querySelector(
                    'input[name="role"]:checked'
                ).value;


            if (selectedRole === 'admin') {

                identityLabel.textContent =
                    'Username';

                identityInput.placeholder =
                    'Enter your username';

                portalSubtitle.textContent =
                    'Dress at Your Door • Admin Control Panel';

                extraLinks.innerHTML =
                    'Administrator access only';


            } else if (
                selectedRole === 'seller'
            ) {

                identityLabel.textContent =
                    'Email';

                identityInput.placeholder =
                    'Enter your seller email';

                portalSubtitle.textContent =
                    'Dress at Your Door • Seller Portal';

                extraLinks.innerHTML =
                    'Want to sell? <a href="become_seller.php">Become a Seller</a>';


            } else {

                identityLabel.textContent =
                    'Email';

                identityInput.placeholder =
                    'Enter your email';

                portalSubtitle.textContent =
                    'Dress at Your Door • Customer Portal';

                extraLinks.innerHTML =
                    'New here? <a href="user_registration.php">Create an account</a>';
            }
        }


        roleInputs.forEach(function(input) {

            input.addEventListener(
                'change',
                updateRoleUI
            );
        });


        updateRoleUI();
    </script>


</body>

</html>