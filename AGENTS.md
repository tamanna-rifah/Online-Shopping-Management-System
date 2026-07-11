# Repository Guidelines

## Project Structure & Module Organization
This repository is a flat procedural PHP application for an online shopping system. Most request handlers and page controllers live in the project root, for example `index.php`, `cart.php`, `admin_dashboard.php`, and `seller_orders.php`. Shared layout wrappers are in `layout.php` and `seller_layout.php`, while database bootstrap and connection logic live in `db.php`. SQL seed/schema files are `query.sql` and `onlineshopping.sql`. Uploaded runtime assets are stored in `uploads/`; bundled sample product images live in `product image/`.

## Build, Test, and Development Commands
Run the app with XAMPP on Windows:

```powershell
# Start Apache and MySQL from XAMPP, then open:
http://localhost/onlineshopping/onlineshopping/
```

Useful local checks:

```powershell
php -l .\index.php
php -l .\db.php
```

`php -l` performs a syntax check on a file before you test it in the browser. Import `query.sql` through phpMyAdmin if the database is not auto-created by `db.php`.

## Coding Style & Naming Conventions
Follow the existing procedural PHP style: keep page-level logic in the matching `.php` file and reuse shared includes instead of introducing duplicate markup. Use 4-space indentation, braces on the same line, and descriptive snake_case names such as `add_to_cart.php` or `$seller_id`. Keep SQL explicit and prefer prepared statements for any query that uses request data.

## Testing Guidelines
There is no committed automated test suite yet. Before opening a PR, syntax-check every changed PHP file with `php -l` and manually verify the affected flow in the browser, especially login, cart, checkout, admin, and seller pages. If you add validation or DB logic, test both success and failure paths.

## Commit & Pull Request Guidelines
Git history is not available in this workspace, so use clear imperative commit messages such as `Fix seller product image upload`. Keep commits focused on one change. PRs should include a short summary, affected pages, database changes if any, manual test steps, and screenshots for UI updates.

## Security & Configuration Tips
Do not commit real credentials or payment secrets. Keep `db.php` aligned with local XAMPP settings, ensure `uploads/` stays writable, and validate file uploads carefully because this app accepts user-supplied images.
