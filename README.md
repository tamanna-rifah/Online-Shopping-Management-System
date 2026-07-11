# Online Cloth Management and Delivery System

This is a simple PHP (procedural) + MySQL app designed to run on XAMPP on Windows.

## Prerequisites
- XAMPP (Apache + MySQL) installed
- PHP 8.x recommended

## Install & Run
1. Start Apache and MySQL in XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin/` and import the schema:
   - Create database `onlineshopping` (or use any name you prefer).
   - Import `onlineshopping/query.sql`.
3. Verify DB config in `onlineshopping/db.php` matches your MySQL:
   - host: `localhost`
   - user: `root`
   - password: `` (empty by default on XAMPP)
   - db: `onlineshopping`
4. Access the app:
   - URL: `http://localhost/onlineshopping/onlineshopping/`

## Default Accounts
- Admin (from seed data):
  - username: `Rifah`
  - password: `1234`
- Users: register via the UI (`user_registration.php`). Passwords are stored hashed.

## Using the App
### As a Visitor
- Browse products on the home page.
- Filter by category or search by name.
- Click "Add to Cart"; you'll be asked to log in or register.

### As a User
1. Register (`User Registration`).
2. Login (`User Login`).
3. `User Dashboard` → view cart.
4. In `Cart`:
   - Update quantity
   - Remove items
   - Purchase: records a sale and clears your cart

### As an Admin
1. Login at `Admin Login` using the admin account above.
2. `Manage Products`:
   - Add products (category, name, price, image). Images saved under `onlineshopping/uploads/`.
   - Delete products (also removes image file).
3. `Sales Details`: view recorded sales.

## Notes & Security
- Queries use prepared statements for login, cart, manage products, and search/category filters.
- Admin login checks credentials from DB. You can hash the admin password by updating `admins.password` with `password_hash` output and then use the same value to login.
- Ensure `onlineshopping/uploads/` is writable by Apache (XAMPP normally is).

## Troubleshooting
- Port in use: change Apache/MySQL ports or stop conflicting apps.
- DB error: confirm DB exists and `db.php` credentials.
- 404: confirm the URL path matches your folder structure.


