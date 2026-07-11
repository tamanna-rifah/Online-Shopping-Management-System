<?php
// admin_dashboard.php — Marketplace Admin Dashboard

declare(strict_types=1);

session_start();

if (
  !isset($_SESSION['admin'])
  || $_SESSION['admin'] !== true
) {
  header('Location: login.php');
  exit();
}

require_once __DIR__ . '/db.php';


/*
|--------------------------------------------------------------------------
| SAFE OUTPUT
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
  function e(string $value): string
  {
    return htmlspecialchars(
      $value,
      ENT_QUOTES,
      'UTF-8'
    );
  }
}


/*
|--------------------------------------------------------------------------
| COUNT TOTAL SELLERS
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM sellers
");

$row = $result
  ? $result->fetch_assoc()
  : [];

$totalSellers =
  (int) ($row['total'] ?? 0);


/*
|--------------------------------------------------------------------------
| COUNT TOTAL USERS
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
");

$row = $result
  ? $result->fetch_assoc()
  : [];

$totalUsers =
  (int) ($row['total'] ?? 0);


/*
|--------------------------------------------------------------------------
| COUNT TOTAL ORDERS
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
");

$row = $result
  ? $result->fetch_assoc()
  : [];

$totalOrders =
  (int) ($row['total'] ?? 0);


/*
|--------------------------------------------------------------------------
| GET DELIVERED SALES AND PLATFORM COMMISSION
|--------------------------------------------------------------------------
|
| We calculate from order_items instead of orders.grand_total.
| This matches the seller Sales & Earnings calculation.
|
*/

$result = $conn->query("
    SELECT
        COALESCE(
            SUM(oi.quantity * oi.price),
            0
        ) AS delivered_sales

    FROM order_items oi

    INNER JOIN orders o
        ON o.id = oi.order_id

    WHERE LOWER(o.status) = 'delivered'
");

$row = $result
  ? $result->fetch_assoc()
  : [];

$totalDeliveredSales =
  (float) ($row['delivered_sales'] ?? 0);

$platformCommission =
  $totalDeliveredSales * 0.15;


/*
|--------------------------------------------------------------------------
| COUNT DELIVERED ORDERS
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE LOWER(status) = 'delivered'
");

$row = $result
  ? $result->fetch_assoc()
  : [];

$deliveredOrders =
  (int) ($row['total'] ?? 0);


/*
|--------------------------------------------------------------------------
| GET RECENT ORDERS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        o.id,
        o.user_email,
        COALESCE(u.name, '') AS customer_name,
        o.created_at,
        o.status,
        o.grand_total

    FROM orders o

    LEFT JOIN users u
        ON u.email = o.user_email

    ORDER BY
        o.created_at DESC,
        o.id DESC

    LIMIT 10
");

$stmt->execute();

$result = $stmt->get_result();

$orders = $result
  ? $result->fetch_all(MYSQLI_ASSOC)
  : [];

$stmt->close();


/*
|--------------------------------------------------------------------------
| BUILD PAGE CONTENT
|--------------------------------------------------------------------------
*/

ob_start();
?>

<style>
  .admin-stat-number {
    font-size: 1.8rem;
    font-weight: 800;
    margin-top: 8px;
  }

  .admin-stat-note {
    color: var(--muted);
    font-size: .82rem;
    margin-top: 7px;
  }

  .commission-card {
    border: 1px solid rgba(16, 185, 129, .35);
    background:
      linear-gradient(135deg,
        rgba(16, 185, 129, .08),
        var(--panel));
  }

  .sales-card {
    border: 1px solid rgba(37, 117, 252, .30);
    background:
      linear-gradient(135deg,
        rgba(37, 117, 252, .07),
        var(--panel));
  }

  .table-wrap {
    overflow-x: auto;
  }

  /* Four dashboard cards across the full available width */
  .grid .col-3 {
    grid-column: span 3;
    min-width: 0;
  }

  @media (max-width: 1000px) {
    .grid .col-3 {
      grid-column: span 6;
    }
  }

  @media (max-width: 650px) {
    .grid .col-3 {
      grid-column: span 12;
    }
  }
</style>


<div class="grid">


  <!-- TOTAL SELLERS -->

  <div class="card col-3">

    <h3>Total Sellers</h3>

    <div class="admin-stat-number">
      <?= $totalSellers ?>
    </div>

    <div class="admin-stat-note">
      Registered marketplace sellers
    </div>

  </div>


  <!-- TOTAL USERS -->

  <div class="card col-3">

    <h3>Total Users</h3>

    <div class="admin-stat-number">
      <?= $totalUsers ?>
    </div>

    <div class="admin-stat-note">
      Registered customers
    </div>

  </div>


  <!-- TOTAL ORDERS -->

  <div class="card col-3">

    <h3>Total Orders</h3>

    <div class="admin-stat-number">
      <?= $totalOrders ?>
    </div>

    <div class="admin-stat-note">
      All marketplace orders
    </div>

  </div>


  <!-- PLATFORM COMMISSION -->

  <div class="card col-3 commission-card">

    <h3>Platform Earnings (15%)</h3>

    <div class="admin-stat-number">

      Tk <?= number_format(
            $platformCommission,
            2
          ) ?>

    </div>

    <div class="admin-stat-note">
      Commission from delivered sales
    </div>

  </div>


  <!-- DELIVERED SALES -->

  <div class="card col-12 sales-card">

    <h3>Marketplace Sales Overview</h3>

    <div
      style="
                display:flex;
                align-items:center;
                gap:40px;
                flex-wrap:wrap;
                margin-top:15px;
            ">

      <div>

        <div
          style="
                        color:var(--muted);
                        font-size:.85rem;
                    ">
          Delivered Sales
        </div>

        <div class="admin-stat-number">

          Tk <?= number_format(
                $totalDeliveredSales,
                2
              ) ?>

        </div>

      </div>


      <div>

        <div
          style="
                        color:var(--muted);
                        font-size:.85rem;
                    ">
          Delivered Orders
        </div>

        <div class="admin-stat-number">
          <?= $deliveredOrders ?>
        </div>

      </div>


      <div>

        <div
          style="
                        color:var(--muted);
                        font-size:.85rem;
                    ">
          Sellers' Share (85%)
        </div>

        <div class="admin-stat-number">

          Tk <?= number_format(
                $totalDeliveredSales * 0.85,
                2
              ) ?>

        </div>

      </div>

    </div>

  </div>


  <!-- RECENT ORDERS -->

  <div
    class="card col-12"
    role="region"
    aria-label="Recent orders">

    <h3>Recent Orders</h3>

    <div class="table-wrap">

      <table
        class="table"
        aria-label="Recent orders table">

        <thead>

          <tr>
            <th>Order #</th>
            <th>Customer</th>
            <th>Date</th>
            <th>Status</th>
            <th>Total</th>
          </tr>

        </thead>

        <tbody>


          <?php if (empty($orders)): ?>

            <tr>

              <td
                colspan="5"
                style="
                                    text-align:center;
                                    color:var(--muted);
                                    padding:30px;
                                ">
                No orders found.
              </td>

            </tr>


          <?php else: ?>


            <?php foreach ($orders as $order): ?>


              <?php

              $orderId =
                (int) $order['id'];

              $customerName =
                trim(
                  (string)
                  $order['customer_name']
                );

              $customer =
                $customerName !== ''
                ? $customerName
                : (string)
                $order['user_email'];

              $date =
                !empty($order['created_at'])
                ? date(
                  'Y-m-d H:i',
                  strtotime(
                    (string)
                    $order['created_at']
                  )
                )
                : '—';

              $status =
                (string) $order['status'];

              $statusLower =
                strtolower($status);

              $badgeClass = 'badge';

              if (
                $statusLower ===
                'delivered'
              ) {

                $badgeClass =
                  'badge badge--ok';
              } elseif (
                $statusLower ===
                'pending'
                || $statusLower ===
                'cancelled'
              ) {

                $badgeClass =
                  'badge badge--warn';
              }

              ?>


              <tr>

                <td>

                  <a
                    class="link"
                    href="sales_details.php?order=<?= $orderId ?>">
                    #<?= $orderId ?>
                  </a>

                </td>


                <td>
                  <?= e($customer) ?>
                </td>


                <td>
                  <?= e($date) ?>
                </td>


                <td>

                  <span
                    class="<?= $badgeClass ?>">
                    <?= e(
                      ucfirst($status)
                    ) ?>
                  </span>

                </td>


                <td style="font-weight:800;">

                  Tk <?= number_format(
                        (float)
                        $order['grand_total'],
                        2
                      ) ?>

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


/*
|--------------------------------------------------------------------------
| PAGE SETTINGS
|--------------------------------------------------------------------------
*/

$pageTitle =
  'Marketplace Dashboard';

$actions = [
  [
    'href' =>
    'sales_details.php',

    'label' =>
    'View All Orders',

    'brand' =>
    true
  ]
];


/*
|--------------------------------------------------------------------------
| LOAD ADMIN LAYOUT
|--------------------------------------------------------------------------
*/

include __DIR__ . '/layout.php';
