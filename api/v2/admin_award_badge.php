<?php
/**
 * API: Award Badge Manually (Admin Only)
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/xp_system.php';
secure_session_start();

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Admin access required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = intval($data['user_id'] ?? 0);
$badge_id = intval($data['badge_id'] ?? 0);

if (!$user_id || !$badge_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

$success = award_badge($user_id, $badge_id);

if ($success) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Badge awarded successfully'
    ]);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Badge already awarded or failed']);
}
