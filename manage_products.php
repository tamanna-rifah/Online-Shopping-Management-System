<?php
// manage_products.php — uses layout shell palette & actions
declare(strict_types=1);
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

require __DIR__ . '/db.php';

/* CSRF helpers */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function csrf_input(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') . '">';
}
function check_csrf(): void
{
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}
// Helper
function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/* State */
$errors = [];
$notice = '';

$allowedCategories = ['Children', 'Men', 'Women'];
$allowedSort       = [
    'newest' => 'id DESC',
    'oldest' => 'id ASC',
    'price_asc'  => 'price ASC, id DESC',
    'price_desc' => 'price DESC, id DESC',
    'name_asc'   => 'name ASC, id DESC',
    'name_desc'  => 'name DESC, id DESC',
];

/* POST: delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res  = $stmt->get_result();
    $row  = $res->fetch_assoc();

    $del = $conn->prepare("DELETE FROM products WHERE id = ?");
    $del->bind_param('i', $id);
    $del->execute();

    if ($del->affected_rows > 0 && $row && !empty($row['image'])) {
        $path = __DIR__ . '/uploads/' . $row['image'];
        if (is_file($path)) @unlink($path);
    }
    $notice = 'Product deleted.';
}

/* POST: update */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    check_csrf();
    $id       = (int)($_POST['id'] ?? 0);
    $category = trim((string)($_POST['category'] ?? ''));
    $name     = trim((string)($_POST['name'] ?? ''));
    $priceRaw = (string)($_POST['price'] ?? '');
    $price    = is_numeric($priceRaw) ? (float)$priceRaw : null;

    if ($id <= 0) $errors[] = 'Invalid product.';
    if ($category === '' || !in_array($category, $allowedCategories, true)) $errors[] = 'Choose a valid category.';
    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Name is required (≤ 100 chars).';
    if ($price === null || $price < 0 || $price > 9999999) $errors[] = 'Invalid price.';

    $newImageName = null;
    $hasNewImage  = isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = '';

    if ($hasNewImage) {
        $img = $_FILES['image'];
        if ($img['size'] > 5 * 1024 * 1024) $errors[] = 'Image too large (max 5MB).';
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($img['tmp_name']) ?: '';
        if (!isset($allowedMimes[$mime])) $errors[] = 'Only JPG, PNG, GIF, or WEBP allowed.';
    }

    if (!$errors) {
        $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $row  = $res->fetch_assoc();
        if (!$row) $errors[] = 'Product not found.';
    }

    if (!$errors && $hasNewImage) {
        if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
        $ext = $allowedMimes[$mime];
        $safeBase = preg_replace('/[^A-Za-z0-9_\-.]/', '_', basename($_FILES['image']['name']));
        $newImageName = time() . '_' . ($safeBase ?: ('image.' . $ext));
        if (!preg_match('/\.' . preg_quote($ext, '/') . '$/i', $newImageName)) $newImageName .= '.' . $ext;
        $dest = __DIR__ . '/uploads/' . $newImageName;
        if (!(is_uploaded_file($_FILES['image']['tmp_name']) && move_uploaded_file($_FILES['image']['tmp_name'], $dest))) {
            $errors[] = 'Failed to save new image.';
        }
    }

    if (!$errors) {
        if ($hasNewImage) {
            $stmt = $conn->prepare("UPDATE products SET category=?, name=?, price=?, image=? WHERE id=?");
            $stmt->bind_param('ssdsi', $category, $name, $price, $newImageName, $id);
        } else {
            $stmt = $conn->prepare("UPDATE products SET category=?, name=?, price=? WHERE id=?");
            $stmt->bind_param('ssdi', $category, $name, $price, $id);
        }
        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            if ($hasNewImage && !empty($row['image'])) {
                $old = __DIR__ . '/uploads/' . $row['image'];
                if (is_file($old)) @unlink($old);
            }
            // redirect to clear POST
            header('Location: manage_products.php?' . http_build_query([
                'q' => (string)($_GET['q'] ?? ''),
                'cat' => (string)($_GET['cat'] ?? ''),
                'sort' => (string)($_GET['sort'] ?? 'newest'),
                'page' => (int)($_GET['page'] ?? 1),
            ]));
            exit();
        } else {
            $errors[] = 'No changes detected or update failed.';
        }
    }
}

/* List query params */
$q    = trim((string)($_GET['q'] ?? ''));
$cat  = trim((string)($_GET['cat'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'newest');
if (!isset($allowedSort[$sort])) $sort = 'newest';

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

/* WHERE */
$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $where[] = "name LIKE CONCAT('%', ?, '%')";
    $params[] = $q;
    $types .= 's';
}
if ($cat !== '' && in_array($cat, $allowedCategories, true)) {
    $where[] = "category = ?";
    $params[] = $cat;
    $types .= 's';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* Count */
$sqlCount = "SELECT COUNT(*) AS c FROM products $whereSql";
$stmt = $conn->prepare($sqlCount);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['c'];
$pages = max(1, (int)ceil($total / $per_page));

/* Page fetch */
$sql = "SELECT id, category, name, price, image FROM products $whereSql ORDER BY {$allowedSort[$sort]} LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($params) {
    $types2 = $types . 'ii';
    $params2 = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($types2, ...$params2);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$rows = $stmt->get_result();

/* Current edit */
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editRow = null;
if ($editId > 0) {
    $s = $conn->prepare("SELECT id, category, name, price, image FROM products WHERE id = ?");
    $s->bind_param('i', $editId);
    $s->execute();
    $editRow = $s->get_result()->fetch_assoc();
}

/* Layout header config (appears in layout.php topbar) */
$pageTitle = 'Manage Products';
$actions = [
    ['href' => 'add_product.php', 'label' => '➕ Add New Product', 'brand' => true],
];

// ---------- Build inner page content (keeps your existing page design) ----------
ob_start(); ?>
<style>
    /* Reuse layout variables (no :root override) */
    .wrap {
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 14px 40px
    }

    h1 {
        margin: 0 0 12px
    }

    /* Buttons aligned to layout system */
    .btn {
        border: 1px solid var(--border);
        background: var(--panel);
        color: var(--text);
        padding: 10px 14px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block
    }

    .btn-blue {
        background: linear-gradient(135deg, var(--brand), var(--brand-2));
        border: none;
        color: #fff
    }

    .btn-blue:hover {
        filter: brightness(1.05)
    }

    .btn-green {
        background: rgba(16, 185, 129, .18);
        border: 1px solid var(--border);
        color: var(--text)
    }

    .btn-green:hover {
        background: rgba(16, 185, 129, .28)
    }

    .btn-red {
        background: rgba(245, 158, 11, .18);
        border: 1px solid var(--border);
        color: var(--text);
        cursor: pointer
    }

    .btn-red:hover {
        background: rgba(245, 158, 11, .28)
    }

    /* Cards & text */
    .card {
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px;
        box-shadow: var(--shadow);
        color: var(--text)
    }

    .muted {
        color: var(--muted);
        font-size: .9em
    }

    /* Filters */
    .filters {
        display: grid;
        grid-template-columns: 1fr 180px 180px 120px;
        gap: 10px
    }

    input,
    select {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: var(--panel);
        color: var(--text)
    }

    /* Alerts in layout palette */
    .alert {
        max-width: 1100px;
        margin: 10px auto;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid var(--border)
    }

    .alert-ok {
        background: rgba(16, 185, 129, .12);
        color: var(--text)
    }

    .alert-err {
        background: rgba(245, 158, 11, .12);
        color: var(--text)
    }

    /* Table like layout */
    .table {
        width: 100%;
        border-collapse: collapse;
        overflow: hidden;
        border-radius: 12px
    }

    .table th,
    .table td {
        padding: 12px 14px;
        text-align: left;
        border-bottom: 1px solid var(--border)
    }

    .table th {
        font-size: .9rem;
        color: var(--muted);
        font-weight: 800;
        letter-spacing: .3px;
        background: linear-gradient(180deg, var(--panel-2), var(--panel))
    }

    .row-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap
    }

    .thumb {
        width: 80px;
        height: 80px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid var(--border);
        background: var(--panel-2)
    }

    /* Edit form */
    .edit-form {
        margin-top: 16px
    }

    .edit-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px
    }

    .edit-grid label {
        font-weight: 700;
        color: var(--muted)
    }

    /* Pagination */
    .pagination {
        display: flex;
        gap: 6px;
        justify-content: center;
        margin-top: 16px;
        flex-wrap: wrap
    }

    .pagination a,
    .pagination span {
        background: var(--panel);
        padding: 8px 12px;
        border-radius: 8px;
        text-decoration: none;
        color: var(--text);
        border: 1px solid var(--border)
    }

    .pagination .active {
        background: linear-gradient(135deg, var(--brand), var(--brand-2));
        color: #fff;
        border: none
    }
</style>

<div class="wrap">

    <?php if ($errors): ?>
        <div class="alert alert-err">
            <ul style="margin:0 0 0 18px"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul>
        </div>
    <?php elseif ($notice): ?>
        <div class="alert alert-ok"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:12px">
        <form class="filters" method="GET">
            <input type="text" name="q" placeholder="Search by name…" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
            <select name="cat">
                <option value="">All categories</option>
                <?php foreach ($allowedCategories as $c): ?>
                    <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>" <?= $cat === $c ? 'selected' : ''; ?>><?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <select name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low → High</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High → Low</option>
                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : ''; ?>>Name: A → Z</option>
                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : ''; ?>>Name: Z → A</option>
            </select>
            <button class="btn btn-blue" type="submit">Apply</button>
        </form>
        <div class="muted" style="margin-top:8px">
            Showing <?= $total ? ($offset + 1) : 0 ?>–<?= min($offset + $per_page, $total) ?> of <?= $total ?> result(s)
        </div>
    </div>

    <?php if ($editRow): ?>
        <div class="card edit-form">
            <h3 style="margin:0 0 10px">Edit Product #<?= (int)$editRow['id'] ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_input(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">

                <div class="edit-grid">
                    <div>
                        <label for="category">Category</label>
                        <select name="category" id="category" required>
                            <?php foreach ($allowedCategories as $c): ?>
                                <option value="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>" <?= $editRow['category'] === $c ? 'selected' : ''; ?>><?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="name">Name</label>
                        <input type="text" name="name" id="name" maxlength="100" required value="<?= htmlspecialchars($editRow['name'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="price">Price</label>
                        <input type="number" step="0.01" min="0" name="price" id="price" required value="<?= htmlspecialchars((string)$editRow['price'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label>Current Image</label><br>
                        <img class="thumb" src="uploads/<?= htmlspecialchars($editRow['image'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                    </div>
                    <div style="grid-column:1/-1">
                        <label for="image">Replace Image (optional)</label>
                        <input type="file" name="image" id="image" accept="image/*">
                        <div class="muted">Max 5MB. Allowed: JPG, PNG, GIF, WEBP.</div>
                    </div>
                </div>
                <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap">
                    <button class="btn btn-green" type="submit">Save changes</button>
                    <a class="btn" href="manage_products.php?<?= http_build_query(['q' => $q, 'cat' => $cat, 'sort' => $sort, 'page' => $page]) ?>">Cancel</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-top:12px">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:100px">Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th style="width:140px">Price</th>
                    <th style="width:210px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($p = $rows->fetch_assoc()): ?>
                    <tr>
                        <td><img class="thumb" src="uploads/<?= htmlspecialchars($p['image'], ENT_QUOTES, 'UTF-8') ?>" alt=""></td>
                        <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($p['category'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>Tk <?= htmlspecialchars(number_format((float)$p['price'], 2), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="btn btn-blue" href="manage_products.php?<?= http_build_query([
                                                                                        'q' => $q,
                                                                                        'cat' => $cat,
                                                                                        'sort' => $sort,
                                                                                        'page' => $page,
                                                                                        'edit' => (int)$p['id']
                                                                                    ]) ?>">✏️ Edit</a>
                                <form method="POST" onsubmit="return confirm('Delete this product?');" style="display:inline-block">
                                    <?= csrf_input(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button class="btn btn-red" type="submit">🗑️ Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($total === 0): ?>
                    <tr>
                        <td colspan="5" class="muted">No products found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="manage_products.php?<?= http_build_query(['q' => $q, 'cat' => $cat, 'sort' => $sort, 'page' => $i]) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();

// Render inside the admin shell
include __DIR__ . '/layout.php';
