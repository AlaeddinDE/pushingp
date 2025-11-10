<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$data = json_decode(file_get_contents('php://input'), true);
$target_id = intval($data['target_id'] ?? 0);
$call_type = $data['call_type'] ?? 'voice'; // voice or video

if ($target_id <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid target']);
    exit;
}

// Check if target exists
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ? AND status = 'active'");
$stmt->bind_param('i', $target_id);
$stmt->execute();
$stmt->bind_result($target_name);
if (!$stmt->fetch()) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'error' => 'User not found']);
    exit;
}
$stmt->close();

// Create call entry
$stmt = $conn->prepare("
    INSERT INTO chat_calls (caller_id, receiver_id, call_type, status)
    VALUES (?, ?, ?, 'ringing')
");
$stmt->bind_param('iis', $user_id, $target_id, $call_type);
$stmt->execute();
$call_id = $stmt->insert_id;
$stmt->close();

echo json_encode([
    'status' => 'success',
    'call_id' => $call_id,
    'target_name' => $target_name
]);
