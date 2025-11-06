<?php
session_start();

if (!isset($_SESSION['user'])) {
    $loginPath = dirname(dirname($_SERVER['PHP_SELF']));
    if ($loginPath === '/') {
        $loginPath = '';
    }
    header('Location: ' . $loginPath . '/login.php');
    exit;
}

$sessionUser = $_SESSION['user'];
$userName = '';
$userId = null;
$userRoles = [];

if (is_array($sessionUser)) {
    $userName = $sessionUser['display_name'] ?? $sessionUser['name'] ?? '';
    $userId = $sessionUser['id'] ?? $sessionUser['member_id'] ?? null;
    $userRoles = is_array($sessionUser['roles'] ?? null)
        ? $sessionUser['roles']
        : array_filter(array_map('trim', explode(',', (string) ($sessionUser['roles'] ?? ''))));
} else {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/functions.php';
    $userName = (string) $sessionUser;
    $member = fetch_member_by_name($mysqli, $userName);
    if ($member !== null) {
        $userId = $member['id'];
        $userRoles = !empty($_SESSION['is_admin']) ? ['member', 'admin'] : ['member'];
        $_SESSION['user'] = [
            'id' => $userId,
            'name' => $member['name'],
            'display_name' => $member['name'],
            'flag' => $member['flag'] ?? null,
            'roles' => $userRoles,
        ];
    }
}

if ($userName === '' || $userId === null) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

$isAdmin = in_array('admin', $userRoles, true) || !empty($_SESSION['is_admin']);
$user = $userName;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$memberId = $userId;
?>
