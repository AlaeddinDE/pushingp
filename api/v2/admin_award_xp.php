<?php
/**
 * API: Manual XP Award (Admin Only)
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
$xp_amount = intval($data['xp_amount'] ?? 0);
$reason = trim($data['reason'] ?? 'Manuelle XP-Vergabe (Admin)');

if (!$user_id || $xp_amount == 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

// Create temporary action code for manual awards
$action_code = $xp_amount > 0 ? 'admin_award' : 'admin_penalty';

// Check if action exists, if not create it
global $conn;
$check = $conn->query("SELECT id FROM xp_actions WHERE action_code = '$action_code'");
if ($check->num_rows == 0) {
    $name = $xp_amount > 0 ? 'Admin Belohnung' : 'Admin Strafe';
    $stmt = $conn->prepare("INSERT INTO xp_actions (action_code, action_name, xp_value, category, description) VALUES (?, ?, ?, 'admin', 'Manuell vom Admin')");
    $stmt->bind_param('ssi', $action_code, $name, $xp_amount);
    $stmt->execute();
    $stmt->close();
}

$result = add_xp($user_id, $action_code, $reason);

if ($result['success']) {
    echo json_encode([
        'status' => 'success',
        'data' => $result
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => $result['error'] ?? 'Failed to add XP'
    ]);
}
