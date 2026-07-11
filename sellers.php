<?php
// sellers.php — Admin Seller Management

declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

require_once __DIR__ . '/db.php';
if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

/* CSRF token */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf'];

/* Fetch all sellers */
$stmt = $conn->prepare("
    SELECT
        id,
        name,
        shop_name,
        email,
        phone,
        status,
        created_at
    FROM sellers
    ORDER BY
        CASE status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
            ELSE 4
        END,
        created_at DESC
");

$stmt->execute();

$result = $stmt->get_result();

$sellers = $result
    ? $result->fetch_all(MYSQLI_ASSOC)
    : [];

$stmt->close();


/* Build page content */
ob_start();
?>

<div class="grid">

    <div
        class="card col-12"
        role="region"
        aria-label="Seller list">

        <h3>Seller Applications & Accounts</h3>

        <div class="table-wrap">

            <table
                class="table"
                aria-label="Seller list table">

                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Seller Name</th>

                        <th>Shop Name</th>

                        <th>Email</th>

                        <th>Phone</th>

                        <th>Joined</th>

                        <th>Status</th>

                        <th>Action</th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (!$sellers): ?>


                        <tr>

                            <td
                                colspan="8"
                                style="
                                color:var(--muted);
                                text-align:center;
                            ">

                                No sellers found.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach ($sellers as $seller): ?>


                            <?php

                            $sellerId =
                                (int) $seller['id'];

                            $status =
                                strtolower(
                                    (string) $seller['status']
                                );

                            $badgeClass = 'badge';

                            if ($status === 'approved') {

                                $badgeClass =
                                    'badge badge--ok';
                            } elseif ($status === 'pending') {

                                $badgeClass =
                                    'badge badge--warn';
                            } elseif ($status === 'rejected') {

                                $badgeClass =
                                    'badge badge--danger';
                            }


                            $joined = !empty($seller['created_at'])
                                ? date(
                                    'Y-m-d H:i',
                                    strtotime(
                                        (string)
                                        $seller['created_at']
                                    )
                                )
                                : '—';

                            ?>


                            <tr>


                                <td>

                                    #<?= $sellerId ?>

                                </td>


                                <td>

                                    <?= e(
                                        (string)
                                        $seller['name']
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        (string)
                                        $seller['shop_name']
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        (string)
                                        $seller['email']
                                    ) ?>

                                </td>


                                <td>

                                    <?= e(
                                        (string)
                                        $seller['phone']
                                    ) ?>

                                </td>


                                <td>

                                    <?= e($joined) ?>

                                </td>


                                <td>

                                    <span
                                        class="<?= $badgeClass ?>">

                                        <?= e(
                                            ucfirst($status)
                                        ) ?>

                                    </span>

                                </td>


                                <td>


                                    <?php if ($status === 'pending'): ?>


                                        <!-- APPROVE -->

                                        <form
                                            action="update_seller_status.php"
                                            method="post"
                                            style="
                                            display:inline-block;
                                            margin:2px;
                                        ">

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?= e($csrf) ?>">

                                            <input
                                                type="hidden"
                                                name="seller_id"
                                                value="<?= $sellerId ?>">

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="approved">

                                            <button
                                                type="submit"
                                                class="btn btn--ok"
                                                style="
                                                padding:7px 10px;
                                                font-size:.8rem;
                                            ">

                                                Approve

                                            </button>

                                        </form>


                                        <!-- REJECT -->

                                        <form
                                            action="update_seller_status.php"
                                            method="post"
                                            style="
                                            display:inline-block;
                                            margin:2px;
                                        ">

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?= e($csrf) ?>">

                                            <input
                                                type="hidden"
                                                name="seller_id"
                                                value="<?= $sellerId ?>">

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="rejected">

                                            <button
                                                type="submit"
                                                class="btn btn--danger"
                                                style="
                                                padding:7px 10px;
                                                font-size:.8rem;
                                            ">

                                                Reject

                                            </button>

                                        </form>


                                    <?php elseif ($status === 'approved'): ?>


                                        <form
                                            action="update_seller_status.php"
                                            method="post"
                                            style="
                                            display:inline-block;
                                            margin:2px;
                                        ">

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?= e($csrf) ?>">

                                            <input
                                                type="hidden"
                                                name="seller_id"
                                                value="<?= $sellerId ?>">

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="rejected">

                                            <button
                                                type="submit"
                                                class="btn btn--danger"
                                                style="
                                                padding:7px 10px;
                                                font-size:.8rem;
                                            ">

                                                Block

                                            </button>

                                        </form>


                                    <?php elseif ($status === 'rejected'): ?>


                                        <form
                                            action="update_seller_status.php"
                                            method="post"
                                            style="
                                            display:inline-block;
                                            margin:2px;
                                        ">

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?= e($csrf) ?>">

                                            <input
                                                type="hidden"
                                                name="seller_id"
                                                value="<?= $sellerId ?>">

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="approved">

                                            <button
                                                type="submit"
                                                class="btn btn--ok"
                                                style="
                                                padding:7px 10px;
                                                font-size:.8rem;
                                            ">

                                                Approve

                                            </button>

                                        </form>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>

</div>


<?php

$content = ob_get_clean();

$pageTitle = 'Seller Management';

$actions = [];

include __DIR__ . '/layout.php';
