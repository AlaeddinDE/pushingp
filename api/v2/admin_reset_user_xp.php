<?php
/**
 * API: Reset User XP (Admin Only)
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
secure_session_start();

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Admin access required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = intval($data['user_id'] ?? 0);

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid user ID']);
    exit;
}

// Reset user XP and level
$stmt = $conn->prepare("UPDATE users SET xp_total = 0, level_id = 1 WHERE id = ?");
$stmt->bind_param('i', $user_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    // Log the reset
    $admin_id = get_current_user_id();
    $stmt = $conn->prepare("INSERT INTO xp_history (user_id, action_code, description, xp_change, xp_before, xp_after) VALUES (?, 'admin_reset', 'XP Reset durch Admin', 0, 0, 0)");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'User XP reset to 0'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to reset XP']);
}
