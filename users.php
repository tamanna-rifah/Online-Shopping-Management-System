<?php
// users.php — list users (no pagination)
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: admin_login.php'); exit();
}

require_once __DIR__ . '/db.php'; // MySQLi $conn

if (!function_exists('e')) {
    function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

/* -------------------- Inputs -------------------- */
$q    = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'id');
$dir  = (strtolower((string)($_GET['dir'] ?? 'desc')) === 'asc') ? 'asc' : 'desc';

/* -------------------- Safe defaults -------------------- */
$rows    = [];
$total   = 0;
$dbError = null;

/* -------------------- Sorting + filtering -------------------- */
$sortable = [
    'id'       => 'u.id',
    'name'     => 'u.name',
    'email'    => 'u.email',
    'phone'    => 'u.phone',
    'location' => 'u.location',
];
$orderBy = $sortable[$sort] ?? $sortable['id'];

$whereSql = '';
$kwParam  = null;

if ($q !== '') {
    $whereSql = "WHERE (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.location LIKE ?)";
    $kwParam  = "%{$q}%";
}

/* -------------------- DB work -------------------- */
if (!isset($conn) || !($conn instanceof mysqli)) {
    $dbError = 'Database connection is not available. Please check db.php ($conn).';
} else {
    try {
        if ($q !== '') {
            $sql = "SELECT COUNT(*) AS c FROM users u {$whereSql}";
            $st  = $conn->prepare($sql);
            $st->bind_param('ssss', $kwParam, $kwParam, $kwParam, $kwParam);
            $st->execute();
            $res   = $st->get_result();
            $total = (int)($res->fetch_assoc()['c'] ?? 0);
            $st->close();
        } else {
            $res = $conn->query("SELECT COUNT(*) AS c FROM users u");
            $total = (int)($res->fetch_assoc()['c'] ?? 0);
            $res->close();
        }

        // All rows (⚠️ careful if table is very large)
        $sql = "
            SELECT u.id, u.name, u.email, u.phone, u.location
            FROM users u
            {$whereSql}
            ORDER BY {$orderBy} {$dir}
        ";

        if ($q !== '') {
            $st = $conn->prepare($sql);
            $st->bind_param('ssss', $kwParam, $kwParam, $kwParam, $kwParam);
            $st->execute();
            $res = $st->get_result();
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $st->close();
        } else {
            $res = $conn->query($sql);
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $res->close();
        }
    } catch (Throwable $e) {
        $dbError = 'Database error: ' . e($e->getMessage());
    }
}

/* -------------------- Helpers -------------------- */
function sort_link(string $key, string $label, string $currentSort, string $currentDir): string {
    $dir = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
    $params = $_GET;
    $params['sort'] = $key;
    $params['dir']  = $dir;
    $href = '?' . http_build_query($params);
    $arrow = ($currentSort === $key) ? ($currentDir === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a class="link" href="'.e($href).'">'.e($label.$arrow).'</a>';
}

/* -------------------- Layout vars -------------------- */
$pageTitle = 'Users';
$actions   = [];

/* -------------------- Inner content -------------------- */
ob_start(); ?>
<div class="card">
    <h3>Users</h3>

    <?php if ($dbError): ?>
        <div class="card" style="background:rgba(245,158,11,.08); border-color:#f59e0b; color:#f59e0b; margin-bottom:10px;">
            <strong>Warning:</strong> <?= $dbError ?>
        </div>
    <?php endif; ?>

    <form method="get" style="display:flex; gap:10px; align-items:center; margin: 0 0 12px;">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by name, email, phone, or location"
               style="flex:1; padding:10px 12px; border-radius:10px; border:1px solid var(--border); background:var(--panel-2); color:var(--text);" />
        <button class="btn" type="submit">Search</button>
        <?php if ($q !== ''): ?>
            <a class="btn" href="users.php">Reset</a>
        <?php endif; ?>
    </form>

    <div style="margin: 8px 0 14px;">
        <div class="chip"><span class="dot"></span> <?= number_format($total) ?> users total</div>
    </div>

    <div style="overflow:auto;">
        <table class="table" role="table" aria-label="Users table">
            <thead>
            <tr>
                <th><?= sort_link('id','ID',       $sort, $dir) ?></th>
                <th><?= sort_link('name','Name',   $sort, $dir) ?></th>
                <th><?= sort_link('email','Email', $sort, $dir) ?></th>
                <th><?= sort_link('phone','Phone', $sort, $dir) ?></th>
                <th><?= sort_link('location','Location', $sort, $dir) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" style="color:var(--muted);">No users found.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><?= e($r['name'] ?? '') ?></td>
                    <td>
                        <?php $email = (string)($r['email'] ?? ''); ?>
                        <?php if ($email !== ''): ?>
                            <a class="link" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['phone'] ?? '') ?: '—' ?></td>
                    <td><?= e($r['location'] ?? '') ?: '—' ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();

require __DIR__ . '/layout.php';
