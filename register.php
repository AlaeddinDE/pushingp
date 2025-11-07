<?php
/**
 * REGISTRATION DISABLED
 * Nur Admins können neue User anlegen
 */
require_once __DIR__ . '/includes/functions.php';
secure_session_start();

// Redirect to login
header('Location: login.php');
exit;
