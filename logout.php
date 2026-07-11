<?php
// logout.php - Handle Admin Logout
session_start();
session_destroy();
header("Location: index.php");
?>

<?php