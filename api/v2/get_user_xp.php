<?php
/**
 * API: Get user XP and level info
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

// Only allow viewing own data unless admin
if ($user_id != get_current_user_id() && !is_admin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Access denied']);
    exit;
}

$level_info = get_user_level_info($user_id);
$badges = get_user_badges($user_id);

if (!$level_info) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => 'User not found']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'data' => [
        'level_info' => $level_info,
        'badges' => $badges,
        'badge_count' => count($badges)
    ]
]);
