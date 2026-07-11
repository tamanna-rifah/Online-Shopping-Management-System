<?php
// seller_layout.php — shared seller panel layout

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* SELLER ACCESS PROTECTION */

if (empty($_SESSION['seller_id'])) {
    header('Location: login.php');
    exit();
}

/* SAFE OUTPUT */

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

/* SELLER INFORMATION */

$sellerName = e((string) ($_SESSION['seller_name'] ?? 'Seller'));
$shopName   = e((string) ($_SESSION['seller_shop_name'] ?? 'My Shop'));

/* CSRF */

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf'];

/* PAGE VARIABLES */

$pageTitle = $pageTitle ?? 'Seller Dashboard';
$actions   = $actions ?? [];
$content   = $content ?? '';

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title><?= e($pageTitle) ?></title>

    <style>
        :root {
            --bg: #0f172a;
            --panel: #111827;
            --panel-2: #0b1220;
            --text: #e5e7eb;
            --muted: #9ca3af;

            --brand: #0ea5e9;
            --brand-2: #14b8a6;

            --border: #1f2937;

            --ok: #10b981;
            --warn: #f59e0b;
            --danger: #ef4444;

            --radius: 14px;

            --shadow:
                0 10px 30px rgba(0, 0, 0, .35);
        }

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

            color: var(--text);

            background:
                radial-gradient(1200px 800px at 10% -20%,
                    rgba(14, 165, 233, .18),
                    transparent 70%),

                radial-gradient(900px 700px at 120% 20%,
                    rgba(20, 184, 166, .18),
                    transparent 70%),

                var(--bg);
        }


        /* MAIN LAYOUT */

        .layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100dvh;
        }


        /* SIDEBAR */

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


        /* BRAND */

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;

            margin: 6px 6px 16px;
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
                0 8px 20px rgba(14, 165, 233, .3);
        }

        .brand .title {
            font-weight: 800;
        }

        .brand .sub {
            color: var(--muted);
            font-size: .85rem;
        }


        /* SELLER CARD */

        .user-card {
            margin: 18px 6px 6px;

            padding: 14px;

            border-radius: 12px;

            background:
                linear-gradient(180deg,
                    var(--panel),
                    var(--panel-2));

            border:
                1px solid var(--border);
        }

        .shop-name {
            margin-top: 3px;

            color: var(--muted);

            font-size: .85rem;
        }

        .chip {
            display: inline-flex;

            align-items: center;

            gap: 8px;

            margin-top: 8px;

            padding: 6px 10px;

            border-radius: 24px;

            background: #1f2937;

            color: var(--muted);

            font-size: .8rem;
        }

        .chip .dot {
            width: 8px;
            height: 8px;

            border-radius: 50%;

            background: var(--ok);
        }


        /* NAVIGATION */

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

            width: 100%;

            padding: 12px 14px;

            border-radius: 12px;

            color: var(--text);

            text-decoration: none;

            font-weight: 600;

            cursor: pointer;

            transition: .2s;
        }

        .nav a:hover,
        .nav button:hover {
            background:
                linear-gradient(180deg,
                    rgba(14, 165, 233, .15),
                    rgba(20, 184, 166, .15));

            transform: translateY(-1px);
        }

        .nav a[aria-current="page"] {
            background:
                linear-gradient(135deg,
                    rgba(14, 165, 233, .25),
                    rgba(20, 184, 166, .25));

            border:
                1px solid rgba(14, 165, 233, .35);
        }

        .nav svg {
            width: 20px;
            height: 20px;

            flex-shrink: 0;
        }


        /* TOP BAR */

        .topbar {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 12px;

            padding: 16px 22px;

            border-bottom:
                1px solid var(--border);
        }

        .topbar h1 {
            margin: 0;

            font-size: 1.35rem;
        }


        /* ACTIONS */

        .actions {
            display: flex;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;

            align-items: center;

            gap: 8px;

            padding: 10px 14px;

            border:
                1px solid var(--border);

            border-radius: 10px;

            background: var(--panel);

            color: var(--text);

            text-decoration: none;

            font-weight: 600;

            cursor: pointer;
        }

        .btn--brand {
            border: none;

            background:
                linear-gradient(135deg,
                    var(--brand),
                    var(--brand-2));

            color: white;
        }

        .btn--ok {
            border: none;

            background: var(--ok);

            color: white;
        }

        .btn--warn {
            border: none;

            background: var(--warn);

            color: #111827;
        }


        /* CONTENT */

        .content {
            display: flex;

            flex-direction: column;

            gap: 22px;

            padding: 22px;
        }


        /* GRID */

        .grid {
            display: grid;

            grid-template-columns:
                repeat(12, 1fr);

            gap: 18px;
        }

        .col-3 {
            grid-column: span 3;
        }

        .col-12 {
            grid-column: span 12;
        }


        /* CARD */

        .card {
            background: var(--panel);

            border:
                1px solid var(--border);

            border-radius: var(--radius);

            padding: 18px;

            box-shadow: var(--shadow);
        }

        .card h3 {
            margin: 0 0 10px;

            color: var(--muted);

            font-size: .95rem;
        }

        .stat-number {
            font-size: 1.8rem;

            font-weight: 800;

            margin-top: 6px;
        }


        /* TABLE */

        .table-wrap {
            width: 100%;

            overflow-x: auto;
        }

        .table {
            width: 100%;

            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 12px 14px;

            text-align: left;

            border-bottom:
                1px solid var(--border);
        }

        .table th {
            color: var(--muted);

            font-size: .9rem;

            background: var(--panel-2);
        }


        /* BADGE */

        .badge {
            display: inline-block;

            padding: 6px 10px;

            border-radius: 999px;

            font-size: .78rem;

            font-weight: 800;

            background:
                rgba(14, 165, 233, .14);

            color: var(--brand);
        }

        .badge--ok {
            background:
                rgba(16, 185, 129, .18);

            color: var(--ok);
        }

        .badge--warn {
            background:
                rgba(245, 158, 11, .18);

            color: var(--warn);
        }


        /* FOOTER */

        .footer {
            color: var(--muted);

            font-size: .85rem;

            text-align: center;

            padding: 10px 0 20px;
        }


        /* RESPONSIVE */

        @media (max-width:980px) {

            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;

                height: auto;

                border-right: none;

                border-bottom:
                    1px solid var(--border);
            }

            .col-3 {
                grid-column: span 6;
            }
        }

        @media (max-width:600px) {

            .col-3 {
                grid-column: span 12;
            }

            .topbar {
                align-items: flex-start;

                flex-direction: column;
            }
        }
    </style>

</head>


<body>

    <div class="layout">


        <!-- SIDEBAR -->

        <aside class="sidebar">


            <div class="brand">

                <div class="logo"></div>

                <div>

                    <div class="title">
                        Seller Console
                    </div>

                    <div class="sub">
                        Store Management
                    </div>

                </div>

            </div>


            <div class="user-card">

                <div style="font-weight:800;">
                    <?= $sellerName ?>
                </div>

                <div class="shop-name">
                    <?= $shopName ?>
                </div>

                <div class="chip">

                    <span class="dot"></span>

                    Approved Seller

                </div>

            </div>


            <nav class="nav">


                <a
                    href="seller_dashboard.php"

                    <?= $currentPage === 'seller_dashboard.php'
                        ? 'aria-current="page"'
                        : ''
                    ?>>

                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3l9-8z" />
                    </svg>

                    Dashboard

                </a>


                <a
                    href="seller_add_product.php"

                    <?= $currentPage === 'seller_add_product.php'
                        ? 'aria-current="page"'
                        : ''
                    ?>>

                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11 4h2v7h7v2h-7v7h-2v-7H4v-2h7V4z" />
                    </svg>

                    Add Product

                </a>


                <a
                    href="seller_products.php"

                    <?= $currentPage === 'seller_products.php'
                        ? 'aria-current="page"'
                        : ''
                    ?>>

                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 3h18v4H3V3zm0 6h18v12H3V9zm2 2v8h14v-8H5z" />
                    </svg>

                    My Products

                </a>


                <a
                    href="seller_orders.php"

                    <?= $currentPage === 'seller_orders.php'
                        ? 'aria-current="page"'
                        : ''
                    ?>>

                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 4h18v4H3V4zm2 6h14v10H5V10zm3 3v2h8v-2H8z" />
                    </svg>

                    My Orders

                </a>


                <a
                    href="seller_sales.php"

                    <?= $currentPage === 'seller_sales.php'
                        ? 'aria-current="page"'
                        : ''
                    ?>>

                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 13h2v8H3v-8zm4-6h2v14H7V7zm4 3h2v11h-2V10zm4-6h2v17h-2V4zm4 8h2v9h-2v-9z" />
                    </svg>

                    Sales & Earnings

                </a>


                <form
                    action="seller_logout.php"
                    method="post"
                    style="margin-top:8px;">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e($csrf) ?>">

                    <button
                        type="submit"
                        class="btn">

                        Logout

                    </button>

                </form>


            </nav>

        </aside>



        <!-- MAIN -->

        <main>


            <header class="topbar">

                <h1>
                    <?= e($pageTitle) ?>
                </h1>


                <?php if (!empty($actions)): ?>

                    <div class="actions">

                        <?php foreach ($actions as $action): ?>

                            <a
                                class="
                        btn
                        <?= !empty($action['brand'])
                                ? 'btn--brand'
                                : ''
                        ?>
                    "

                                href="<?= e(
                                            (string) $action['href']
                                        ) ?>">

                                <?= e(
                                    (string) $action['label']
                                ) ?>

                            </a>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </header>



            <section class="content">

                <?= $content ?>

                <div class="footer">

                    © <?= date('Y') ?>

                    Dress at Your Door

                    • Seller Console

                </div>

            </section>


        </main>

    </div>

</body>

</html>