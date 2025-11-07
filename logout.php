<?php
/**
 * Logout - Session beenden
 */
require_once __DIR__ . '/includes/functions.php';

secure_session_start();

// Session komplett löschen
$_SESSION = array();

// Session-Cookie löschen
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session zerstören
session_destroy();

// Redirect zur Startseite
header('Location: index.php');
exit;
