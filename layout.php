<?php
// layout.php — shared REAL ADMIN shell
// Sidebar + topbar + common styles

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =========================================
   ADMIN ACCESS PROTECTION
========================================= */

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

/* =========================================
   SAFE HTML OUTPUT FUNCTION
========================================= */

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================================
   ADMIN INFORMATION
========================================= */

$adminName = isset($_SESSION['admin_name'])
    ? e((string) $_SESSION['admin_name'])
    : 'Administrator';

/* =========================================
   CSRF TOKEN
========================================= */

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf'];

/* =========================================
   PAGE VARIABLES

   Child pages can provide:

   $pageTitle
   $actions
   $content
========================================= */

$pageTitle = $pageTitle ?? 'Admin Dashboard';

$actions = $actions ?? [];

$content = $content ?? '';

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title><?= e($pageTitle) ?></title>

    <meta name="color-scheme" content="light dark">

    <style>
        /* =========================================
           ROOT COLORS
        ========================================= */

        :root {

            --bg: #0f172a;

            --panel: #111827;

            --panel-2: #0b1220;

            --text: #e5e7eb;

            --muted: #9ca3af;

            --brand: #6a11cb;

            --brand-2: #2575fc;

            --border: #1f2937;

            --ok: #10b981;

            --warn: #f59e0b;

            --danger: #ef4444;

            --radius: 14px;

            --shadow:
                0 10px 30px rgba(0, 0, 0, .35);
        }


        /* =========================================
           LIGHT MODE
        ========================================= */

        @media (prefers-color-scheme: light) {

            :root {

                --bg: #f5f7fb;

                --panel: #ffffff;

                --panel-2: #f8fafc;

                --text: #1f2937;

                --muted: #6b7280;

                --border: #e5e7eb;

                --shadow:
                    0 10px 30px rgba(0, 0, 0, .08);
            }
        }


        /* =========================================
           GENERAL
        ========================================= */

        * {
            box-sizing: border-box;
        }

        html,
        body {

            height: 100%;

            margin: 0;

            font-family:
                ui-sans-serif,
                system-ui,
                -apple-system,
                Segoe UI,
                Roboto,
                Arial;

            background:

                radial-gradient(1200px 800px at 10% -20%,
                    rgba(106, 17, 203, .18),
                    transparent 70%),

                radial-gradient(900px 700px at 120% 20%,
                    rgba(37, 117, 252, .18),
                    transparent 70%),

                var(--bg);

            color: var(--text);
        }


        /* =========================================
           MAIN LAYOUT
        ========================================= */

        .layout {

            display: grid;

            grid-template-columns:
                260px 1fr;

            min-height: 100dvh;
        }


        @media (max-width: 980px) {

            .layout {

                grid-template-columns: 1fr;
            }
        }


        /* =========================================
           SIDEBAR
        ========================================= */

        .sidebar {

            position: sticky;

            top: 0;

            height: 100dvh;

            background:

                linear-gradient(180deg,
                    var(--panel-2),
                    var(--panel));

            border-right:
                1px solid var(--border);

            padding: 18px;
        }


        @media (max-width: 980px) {

            .sidebar {

                position: static;

                height: auto;

                border-right: none;

                border-bottom:
                    1px solid var(--border);
            }
        }


        /* =========================================
           BRAND
        ========================================= */

        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            margin:
                6px 6px 16px;
        }


        .brand .logo {

            width: 38px;

            height: 38px;

            border-radius: 12px;

            background:

                linear-gradient(135deg,
                    var(--brand),
                    var(--brand-2));

            box-shadow:

                0 8px 20px rgba(37, 117, 252, .35);
        }


        .brand .title {

            font-weight: 800;

            letter-spacing: .3px;
        }


        .brand .sub {

            color: var(--muted);

            font-size: .85rem;
        }


        /* =========================================
           ADMIN USER CARD
        ========================================= */

        .user-card {

            margin:
                18px 6px 6px;

            padding: 14px;

            border-radius: 12px;

            background:

                linear-gradient(180deg,
                    var(--panel),
                    var(--panel-2));

            border:
                1px solid var(--border);
        }


        .chip {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            background: #1f2937;

            color: var(--muted);

            padding:
                6px 10px;

            border-radius: 24px;

            font-size: .8rem;

            margin-top: 5px;
        }


        .chip .dot {

            width: 8px;

            height: 8px;

            border-radius: 50%;

            background: var(--ok);

            box-shadow:

                0 0 0 3px rgba(16, 185, 129, .15);
        }


        /* =========================================
           NAVIGATION
        ========================================= */

        .nav {

            display: grid;

            gap: 6px;

            margin-top: 18px;
        }


        .nav a,
        .nav button {

            appearance: none;

            border: none;

            background: none;

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                12px 14px;

            border-radius: 12px;

            text-decoration: none;

            color: var(--text);

            font-weight: 600;

            letter-spacing: .2px;

            transition:

                transform .12s,

                background .2s,

                box-shadow .2s;
        }


        .nav a:hover,
        .nav button:hover {

            background:

                linear-gradient(180deg,
                    rgba(106, 17, 203, .15),
                    rgba(37, 117, 252, .15));

            transform:
                translateY(-1px);

            box-shadow:
                var(--shadow);
        }


        .nav a[aria-current="page"] {

            background:

                linear-gradient(135deg,
                    rgba(106, 17, 203, .25),
                    rgba(37, 117, 252, .25));

            border:

                1px solid rgba(37, 117, 252, .35);
        }


        .nav a:focus-visible,
        .nav button:focus-visible {

            outline:
                2px solid var(--brand-2);

            outline-offset: 2px;
        }


        .nav svg {

            width: 20px;

            height: 20px;

            opacity: .9;

            flex-shrink: 0;
        }


        /* =========================================
           TOP BAR
        ========================================= */

        .topbar {

            display: flex;

            align-items: center;

            justify-content:
                space-between;

            padding:
                16px 22px;

            gap: 12px;

            border-bottom:
                1px solid var(--border);

            background:

                linear-gradient(180deg,
                    rgba(0, 0, 0, .04),
                    transparent);
        }


        .topbar h1 {

            margin: 0;

            font-size: 1.35rem;

            letter-spacing: .2px;
        }


        /* =========================================
           ACTION BUTTONS
        ========================================= */

        .actions {

            display: flex;

            gap: 10px;

            align-items: center;

            flex-wrap: wrap;
        }


        .btn {

            border:
                1px solid var(--border);

            background:
                var(--panel);

            color:
                var(--text);

            padding:
                10px 14px;

            border-radius:
                10px;

            font-weight:
                600;

            cursor:
                pointer;

            transition:

                transform .12s,

                background .2s,

                box-shadow .2s;

            text-decoration:
                none;

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;
        }


        .btn:hover {

            transform:
                translateY(-1px);

            box-shadow:
                var(--shadow);
        }


        .btn--brand {

            border: none;

            background:

                linear-gradient(135deg,
                    var(--brand),
                    var(--brand-2));

            color: #ffffff;
        }


        .btn--ok {

            border: none;

            background:
                var(--ok);

            color: #ffffff;
        }


        .btn--warn {

            border: none;

            background:
                var(--warn);

            color: #111827;
        }


        .btn--danger {

            border: none;

            background:
                var(--danger);

            color: #ffffff;
        }


        /* =========================================
           PAGE CONTENT
        ========================================= */

        .content {

            display: flex;

            flex-direction: column;

            gap: 22px;

            padding: 22px;
        }


        /* =========================================
           CARDS
        ========================================= */

        .card {

            background:
                var(--panel);

            border:
                1px solid var(--border);

            border-radius:
                var(--radius);

            padding:
                18px;

            box-shadow:
                var(--shadow);
        }


        .card h3 {

            margin:
                0 0 10px;

            font-size:
                .95rem;

            color:
                var(--muted);

            font-weight:
                700;

            letter-spacing:
                .3px;
        }


        /* =========================================
           GRID
        ========================================= */

        .grid {

            display:
                grid;

            grid-template-columns:
                repeat(12, 1fr);

            gap:
                18px;
        }


        .col-12 {

            grid-column:
                span 12;
        }


        /* =========================================
           TABLE
        ========================================= */

        .table {

            width:
                100%;

            border-collapse:
                collapse;

            overflow:
                hidden;

            border-radius:
                12px;
        }


        .table th,
        .table td {

            padding:
                12px 14px;

            text-align:
                left;

            border-bottom:
                1px solid var(--border);
        }


        .table th {

            font-size:
                .9rem;

            color:
                var(--muted);

            font-weight:
                800;

            letter-spacing:
                .3px;

            background:

                linear-gradient(180deg,
                    var(--panel-2),
                    var(--panel));
        }


        /* =========================================
           BADGES
        ========================================= */

        .badge {

            display:
                inline-block;

            padding:
                6px 10px;

            border-radius:
                999px;

            font-size:
                .78rem;

            font-weight:
                800;

            letter-spacing:
                .3px;

            background:
                rgba(106, 17, 203, .14);

            color:
                var(--brand-2);
        }


        .badge--warn {

            background:
                rgba(245, 158, 11, .18);

            color:
                var(--warn);
        }


        .badge--ok {

            background:
                rgba(16, 185, 129, .18);

            color:
                var(--ok);
        }


        .badge--danger {

            background:
                rgba(239, 68, 68, .18);

            color:
                var(--danger);
        }


        /* =========================================
           LINKS
        ========================================= */

        .link {

            color:
                inherit;

            text-decoration:
                none;

            font-weight:
                800;
        }


        .link:hover {

            text-decoration:
                underline;
        }


        /* =========================================
           RESPONSIVE TABLE
        ========================================= */

        .table-wrap {

            width: 100%;

            overflow-x: auto;
        }


        /* =========================================
           FOOTER
        ========================================= */

        .footer {

            color:
                var(--muted);

            font-size:
                .85rem;

            text-align:
                center;

            padding:
                10px 0 20px;
        }
    </style>

</head>


<body>


    <div class="layout">


        <!-- =========================================
         SIDEBAR
    ========================================== -->

        <aside
            class="sidebar"
            aria-label="Primary">


            <!-- BRAND -->

            <div
                class="brand"
                aria-label="Brand">

                <div
                    class="logo"
                    aria-hidden="true"></div>

                <div>

                    <div class="title">
                        Admin Console
                    </div>

                    <div class="sub">
                        Control Center
                    </div>

                </div>

            </div>


            <!-- ADMIN INFORMATION -->

            <div
                class="user-card"
                role="group"
                aria-label="Signed in user">

                <div
                    style="
                    display:flex;
                    align-items:center;
                    justify-content:space-between;
                    gap:10px;
                ">

                    <div>

                        <div style="font-weight:800;">

                            <?= $adminName ?>

                        </div>


                        <div
                            class="chip"
                            aria-label="Status">

                            <span class="dot"></span>

                            Online

                        </div>

                    </div>


                    <div
                        style="
                        width:42px;
                        height:42px;
                        border-radius:50%;
                        background:
                            linear-gradient(
                                135deg,
                                var(--brand),
                                var(--brand-2)
                            );
                        box-shadow:
                            0 6px 18px
                            rgba(37,117,252,.3);
                    "
                        aria-hidden="true"></div>

                </div>

            </div>


            <!-- =========================================
             ADMIN NAVIGATION
        ========================================== -->

            <nav
                class="nav"
                aria-label="Main navigation">


                <!-- DASHBOARD -->

                <a
                    href="admin_dashboard.php"

                    <?= $currentPage === 'admin_dashboard.php'
                        ? 'aria-current="page"'
                        : ''
                    ?>>

                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor">

                        <path
                            d="
                        M12 3
                        l9 8
                        h-3
                        v9
                        h-5
                        v-6
                        H11
                        v6
                        H6
                        v-9
                        H3
                        l9-8z
                        " />

                    </svg>

                    Dashboard

                </a>


                <!-- SELLERS -->

                <a
                    href="sellers.php"

                    <?= $currentPage === 'sellers.php'
                        ? 'aria-current="page"'
                        : ''
                    ?>>

                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor">

                        <path
                            d="
                        M4 4
                        h16
                        l1 5
                        H3
                        l1-5z

                        M4 11
                        h16
                        v9
                        H4
                        v-9z

                        M8 13
                        v5
                        h8
                        v-5
                        H8z
                        " />

                    </svg>

                    Sellers

                </a>


                <!-- USERS -->

                <a
                    href="users.php"

                    <?= $currentPage === 'users.php'
                        ? 'aria-current="page"'
                        : ''
                    ?>>

                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor">

                        <path
                            d="
                        M16 11
                        c1.66 0
                        2.99-1.34
                        2.99-3

                        S17.66 5
                        16 5

                        s-3 1.34
                        -3 3

                        1.34 3
                        3 3z

                        M8 11
                        c1.66 0
                        2.99-1.34
                        2.99-3

                        S9.66 5
                        8 5

                        5 6.34
                        5 8

                        s1.34 3
                        3 3z

                        M8 13
                        c-2.33 0
                        -7 1.17
                        -7 3.5

                        V19
                        h10
                        v-2.5

                        C11 14.17
                        6.33 13
                        8 13z

                        M16 13
                        c-.29 0
                        -.62.02
                        -.97.05

                        1.16.84
                        1.97 1.95
                        1.97 3.45

                        V19
                        h6
                        v-2.5

                        c0-2.33
                        -4.67-3.5
                        -7-3.5z
                        " />

                    </svg>

                    Users

                </a>


                <!-- ALL ORDERS -->

                <a
                    href="admin_orders.php"

                    <?= $currentPage === 'admin_orders.php'
                        ? 'aria-current="page"'
                        : ''
                    ?>>

                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor">

                        <path
                            d="
                        M3 4
                        h18
                        v4
                        H3
                        V4z

                        M5 10
                        h14
                        v10
                        H5
                        V10z

                        M8 13
                        v2
                        h8
                        v-2
                        H8z
                        " />

                    </svg>

                    All Orders

                </a>


                <!-- SALES & COMMISSION -->

                <a
                    href="admin_sales.php"

                    <?= $currentPage === 'admin_sales.php'
                        ? 'aria-current="page"'
                        : ''
                    ?>>

                    <svg
                        viewBox="0 0 24 24"
                        fill="currentColor">

                        <path
                            d="
                        M3 13
                        h2
                        v8
                        H3
                        v-8z

                        M7 7
                        h2
                        v14
                        H7
                        V7z

                        M11 10
                        h2
                        v11
                        h-2
                        V10z

                        M15 4
                        h2
                        v17
                        h-2
                        V4z

                        M19 12
                        h2
                        v9
                        h-2
                        v-9z
                        " />

                    </svg>

                    Sales & Commission

                </a>


                <!-- LOGOUT -->

                <form
                    action="logout.php"
                    method="post"
                    style="margin-top:8px;">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e($csrf) ?>">


                    <button
                        type="submit"
                        class="btn"
                        aria-label="Log out">

                        <svg
                            viewBox="0 0 24 24"
                            fill="currentColor">

                            <path
                                d="
                            M16 13
                            v-2
                            H7
                            V8
                            l-5 4
                            5 4
                            v-3
                            h9z

                            M19 3
                            H11

                            c-1.1 0
                            -2 .9
                            -2 2

                            v3
                            h2
                            V5
                            h8
                            v14
                            h-8
                            v-3
                            H9
                            v3

                            c0 1.1
                            .9 2
                            2 2

                            h8

                            c1.1 0
                            2-.9
                            2-2

                            V5

                            c0-1.1
                            -.9-2
                            -2-2z
                            " />

                        </svg>

                        Logout

                    </button>

                </form>


            </nav>

        </aside>



        <!-- =========================================
         MAIN AREA
    ========================================== -->

        <main>


            <!-- TOP BAR -->

            <header
                class="topbar"
                role="banner">


                <h1>

                    <?= e($pageTitle) ?>

                </h1>


                <?php if (!empty($actions)): ?>


                    <div
                        class="actions"
                        role="group"
                        aria-label="Quick actions">


                        <?php foreach ($actions as $action): ?>


                            <a

                                class="
                                btn
                                <?= !empty($action['brand'])
                                    ? 'btn--brand'
                                    : ''
                                ?>
                            "

                                href="
                                <?= e(
                                    (string)
                                    $action['href']
                                ) ?>
                            ">

                                <?= e(
                                    (string)
                                    $action['label']
                                ) ?>

                            </a>


                        <?php endforeach; ?>


                    </div>


                <?php endif; ?>


            </header>



            <!-- =========================================
             PAGE CONTENT
        ========================================== -->

            <section
                class="content"
                role="main">


                <?= $content ?>


                <!-- FOOTER -->

                <div class="footer">

                    © <?= date('Y') ?>

                    Dress at Your Door

                    • Admin Console

                    • All rights reserved.

                </div>


            </section>


        </main>


    </div>


</body>

</html>