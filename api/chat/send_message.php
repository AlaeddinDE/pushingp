<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();
$is_admin = is_admin();
$input = json_decode(file_get_contents('php://input'), true);

$type = $input['type'] ?? '';
$id = intval($input['id'] ?? 0);
$message = trim($input['message'] ?? '');

if (!$type || !$id || !$message) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

try {
    if ($type === 'user') {
        // Private message
        $stmt = $conn->prepare("
            INSERT INTO chat_messages (sender_id, receiver_id, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param('iis', $user_id, $id, $message);
        
    } elseif ($type === 'group') {
        // Group message - Admins können in alle Gruppen schreiben
        if (!$is_admin) {
            // Normal user: Check if member
            $check = $conn->query("
                SELECT 1 FROM chat_group_members 
                WHERE group_id = $id AND user_id = $user_id
            ");
            
            if (!$check || $check->num_rows === 0) {
                echo json_encode(['status' => 'error', 'error' => 'Not a member of this group']);
                exit;
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO chat_messages (sender_id, group_id, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param('iis', $user_id, $id, $message);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message_id' => $conn->insert_id]);
    } else {
        echo json_encode(['status' => 'error', 'error' => 'Failed to send message']);
    }
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
