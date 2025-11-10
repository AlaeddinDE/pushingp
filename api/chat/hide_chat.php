<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();
$input = json_decode(file_get_contents('php://input'), true);

$chat_type = $input['type'] ?? '';
$chat_id = intval($input['id'] ?? 0);

if (!$chat_type || !$chat_id) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Chat für diesen User verstecken
    $stmt = $conn->prepare("
        INSERT INTO chat_hidden (user_id, chat_type, chat_id, hidden_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE hidden_at = NOW()
    ");
    $stmt->bind_param('isi', $user_id, $chat_type, $chat_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Chat ausgeblendet']);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Failed to hide chat']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
