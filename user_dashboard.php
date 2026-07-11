<?php
session_start();
require 'db.php';

// If not logged in, redirect to login page
if (!isset($_SESSION['user_email'])) {
  header("Location: user_login.php");
  exit();
}

// User email
$user_email = $_SESSION['user_email'];

// Load all products
$products_result = $conn->query("SELECT * FROM products");
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Homepage - Online Cloth Management</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background: #f4f4f4;
    }

    .header {
      padding: 25px 20px;
      background: linear-gradient(135deg, rgba(29, 131, 152, 0.95), rgba(49, 106, 134, 0.95));
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

    .icons {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .cart-icon {
      font-size: 22px;
      text-decoration: none;
      color: white;
      position: relative;
    }

    .dropdown {
      position: relative;
      display: inline-block;
    }

    .dropbtn {
      background: none;
      border: none;
      font-size: 22px;
      color: white;
      cursor: pointer;
    }

    .dropdown-content {
      display: none;
      position: absolute;
      right: 0;
      background: white;
      min-width: 220px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
      border-radius: 8px;
      z-index: 1000;
      padding: 6px 0;
    }

    .dropdown-content p,
    .dropdown-content a,
    .submenu>a {
      padding: 12px 16px;
      margin: 0;
      display: block;
      font-size: 14px;
      color: #333;
      text-decoration: none;
      white-space: nowrap;
    }

    .dropdown-content p {
      font-weight: bold;
      border-bottom: 1px solid #eee;
    }

    .dropdown-content a:hover,
    .submenu>a:hover {
      background: #007BFF;
      color: white;
    }

    /* Open when clicked, not on hover */
    .dropdown.open .dropdown-content {
      display: block;
    }

    /* Submenu (Account) */
    .submenu {
      position: relative;
    }

    .submenu>a {
      padding-right: 34px;
    }

    .submenu>a::after {
      content: '▸';
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
      transition: transform .2s ease;
    }

    .submenu-content {
      display: none;
      position: absolute;
      top: 0;
      right: 100%;
      /* Main menu opens to the right, so submenu opens to the left */
      background: white;
      min-width: 220px;
      border-radius: 8px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
      padding: 6px 0;
    }

    .submenu.open .submenu-content {
      display: block;
    }

    .submenu.open>a::after {
      transform: translateY(-50%) rotate(90deg);
    }

    /* Search bar */
    .search-bar {
      margin: 20px auto 0;
      text-align: center;
    }

    .search-bar form {
      display: flex;
      justify-content: center;
      max-width: 500px;
      margin: auto;
    }

    .search-bar input {
      padding: 10px;
      border-radius: 20px;
      border: none;
      width: 100%;
      outline: none;
    }

    .search-bar button {
      background: #007BFF;
      border: none;
      padding: 10px 15px;
      margin-left: -40px;
      border-radius: 50%;
      cursor: pointer;
    }

    .search-bar button img {
      width: 18px;
      height: 18px;
    }

    /* Products */
    .container {
      margin: 30px;
    }

    .products {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 25px;
    }

    .product {
      background-color: rgba(255, 255, 255, 0.95);
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      text-align: center;
      transition: transform .3s ease, box-shadow .3s ease;
    }

    .product:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
    }

    .product h2 {
      margin: 10px 0;
      font-size: 20px;
    }

    .product img {
      max-width: 100%;
      max-height: 200px;
      object-fit: cover;
      border-radius: 8px;
    }

    .product p {
      margin: 5px 0;
    }

    .btn-group {
      margin-top: 10px;
    }

    .btn {
      padding: 6px 12px;
      border-radius: 6px;
      border: none;
      cursor: pointer;
      font-size: 13px;
      margin: 3px;
      text-decoration: none;
      display: inline-block;
    }

    .btn-cart {
      background-color: #007BFF;
      color: white;
    }

    .btn-cart:hover {
      background-color: #0056b3;
    }

    .btn-details {
      background-color: #28a745;
      color: white;
    }

    .btn-details:hover {
      background-color: #1e7e34;
    }

    footer {
      text-align: center;
      margin: 50px 0 20px;
      font-size: 0.9rem;
      color: black;
    }

    .no-products {
      text-align: center;
      padding: 50px;
      background: rgba(255, 255, 255, 0.8);
      border-radius: 10px;
      font-size: 18px;
      font-weight: bold;
    }
  </style>
</head>

<body>
  <!-- Header -->
  <div class="header">
    <h1>Welcome to Dress at Your Door!</h1>
    <div class="icons">
      <!-- Cart Icon -->
      <a href="cart.php" class="cart-icon" aria-label="Cart">🛒</a>

      <!-- Dropdown -->
      <div class="dropdown">
        <button class="dropbtn" aria-haspopup="true" aria-expanded="false" aria-label="User menu">☰</button>
        <div class="dropdown-content" role="menu" aria-label="User menu">
          <p>👤 Logged in as <?= htmlspecialchars($user_email) ?></p>

          <!-- Nested 'Account' with two options -->
          <div class="submenu">
            <a href="account.php" role="menuitem" aria-haspopup="true" aria-expanded="false">🧑 Account</a>
            <div class="submenu-content" role="menu" aria-label="Account submenu">
              <a href="account_information.php" role="menuitem">📄 Account Information</a>
              <a href="address_book.php" role="menuitem">📬 Address Book</a>
            </div>
          </div>

          <a href="my_orders.php" role="menuitem">📦 My Orders</a>
          <a href="cart.php" role="menuitem">🛒 Cart</a>
          <a href="my_reviews.php" role="menuitem">⭐ My Reviews</a>
          <a href="logout.php" role="menuitem">🚪 Logout</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Search -->
  <div class="search-bar">
    <form method="GET" action="index.php">
      <input type="text" name="search" placeholder="Search products..." required />
      <button type="submit"><img src="search-icon.png" alt="Search" /></button>
    </form>
  </div>

  <!-- Products -->
  <div class="container">
    <div class="products">
      <?php if ($products_result && $products_result->num_rows > 0): ?>
        <?php while ($product = $products_result->fetch_assoc()): ?>
          <div class="product">
            <h2><?= htmlspecialchars($product['name']) ?></h2>
            <img src="uploads<?= (substr($product['image'], 0, 1) === '/' ? '' : '/') . htmlspecialchars($product['image']) ?>"
              alt="<?= htmlspecialchars($product['name']) ?>">
            <p>Category: <?= htmlspecialchars($product['category']) ?></p>
            <p>Price: Tk <?= htmlspecialchars($product['price']) ?></p>
            <div class="btn-group">
              <a href="add_to_cart.php?product_id=<?= (int)$product['id'] ?>" class="btn btn-cart">Add to Cart</a>
              <a href="product_details.php?id=<?= (int)$product['id'] ?>" class="btn btn-details">View Details</a>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="no-products">No products available.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Dropdown/Submenu click-to-toggle JS -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const dropdown = document.querySelector('.dropdown');
      const dropbtn = dropdown?.querySelector('.dropbtn');
      const menu = dropdown?.querySelector('.dropdown-content');

      const submenu = dropdown?.querySelector('.submenu');
      const subTrigger = submenu?.querySelector(':scope > a'); // only the top <a>

      if (!dropdown || !dropbtn || !menu) return;

      // Initial aria setup
      dropbtn.setAttribute('aria-expanded', 'false');
      if (subTrigger) {
        subTrigger.setAttribute('aria-haspopup', 'true');
        subTrigger.setAttribute('aria-expanded', 'false');
      }

      // Main dropdown toggle
      dropbtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        dropbtn.setAttribute('aria-expanded', String(isOpen));

        // Reset submenu if dropdown is closed
        if (!isOpen && submenu && subTrigger) {
          submenu.classList.remove('open');
          subTrigger.setAttribute('aria-expanded', 'false');
        }
      });

      // Submenu (Account) toggle
      if (subTrigger && submenu) {
        subTrigger.addEventListener('click', (e) => {
          // Using Account link as toggle
          e.preventDefault();
          e.stopPropagation();
          const isOpen = submenu.classList.toggle('open');
          subTrigger.setAttribute('aria-expanded', String(isOpen));
        });
      }

      // Prevent bubbling inside the menu
      menu.addEventListener('click', (e) => e.stopPropagation());

      // Close all menus when clicking outside
      document.addEventListener('click', () => {
        dropdown.classList.remove('open');
        dropbtn.setAttribute('aria-expanded', 'false');
        if (submenu && subTrigger) {
          submenu.classList.remove('open');
          subTrigger.setAttribute('aria-expanded', 'false');
        }
      });

      // Keyboard support: Close with Escape
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          dropdown.classList.remove('open');
          dropbtn.setAttribute('aria-expanded', 'false');
          if (submenu && subTrigger) {
            submenu.classList.remove('open');
            subTrigger.setAttribute('aria-expanded', 'false');
          }
        }
        // Toggle with Space/Enter (Accessibility)
        if ((e.key === ' ' || e.key === 'Enter')) {
          if (document.activeElement === dropbtn) {
            e.preventDefault();
            dropbtn.click();
          } else if (document.activeElement === subTrigger) {
            e.preventDefault();
            subTrigger.click();
          }
        }
      });
    });
  </script>
</body>

</html>