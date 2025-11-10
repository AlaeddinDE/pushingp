<?php
/**
 * API: Toggle XP Action Status (Admin Only)
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
$action_id = intval($data['action_id'] ?? 0);
$is_active = intval($data['is_active'] ?? 0);

if (!$action_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid action ID']);
    exit;
}

$stmt = $conn->prepare("UPDATE xp_actions SET is_active = ? WHERE id = ?");
$stmt->bind_param('ii', $is_active, $action_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode([
        'status' => 'success',
        'message' => 'XP Action status updated'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Failed to update']);
}
