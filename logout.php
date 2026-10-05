<?php
require_once __DIR__ . '/auth.php';
start_auth_session();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        (bool) ($params['secure'] ?? false),
        (bool) ($params['httponly'] ?? true)
    );
}

session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=index.php">
    <title>Logging out</title>
</head>
<body>
<script>
localStorage.removeItem('dyod_user_logged_in');
localStorage.removeItem('dyod_user_email');
window.location.replace('index.php');
</script>
</body>
</html>
