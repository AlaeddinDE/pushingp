<?php
session_start();
if (!isset($_SESSION['user'])) {
    // Dynamischer Pfad basierend auf aktuellem Verzeichnis
    $loginPath = dirname(dirname($_SERVER['PHP_SELF']));
    if ($loginPath === '/') $loginPath = '';
    header("Location: " . $loginPath . "/login.php");
    exit;
}
$user = $_SESSION['user'];
$isAdmin = $_SESSION['is_admin'] ?? false;
?>
