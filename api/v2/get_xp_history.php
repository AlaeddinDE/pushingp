<?php
/**
 * API: Get XP History
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/xp_system.php';
secure_session_start();

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}

$user_id = $_GET['user_id'] ?? get_current_user_id();
$limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 50;

// Only allow viewing own data unless admin
if ($user_id != get_current_user_id() && !is_admin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Access denied']);
    exit;
}

$history = get_xp_history($user_id, $limit);

echo json_encode([
    'status' => 'success',
    'data' => $history
]);
