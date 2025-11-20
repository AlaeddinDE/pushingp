<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$chat_type = $_POST['chat_type'] ?? 'user';
$chat_id = intval($_POST['chat_id'] ?? 0);

if ($chat_id <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid chat ID']);
    exit;
}

// Get all unread messages in this chat
if ($chat_type === 'user') {
    $stmt = $conn->prepare("
        SELECT id FROM chat_messages 
        WHERE sender_id = ? 
        AND receiver_id = ? 
        AND group_id IS NULL
        AND id NOT IN (
            SELECT message_id FROM chat_read_receipts WHERE user_id = ?
        )
    ");
    $stmt->bind_param('iii', $chat_id, $user_id, $user_id);
} else {
    $stmt = $conn->prepare("
        SELECT id FROM chat_messages 
        WHERE group_id = ?
        AND sender_id != ?
        AND id NOT IN (
            SELECT message_id FROM chat_read_receipts WHERE user_id = ?
        )
    ");
    $stmt->bind_param('iii', $chat_id, $user_id, $user_id);
}

$stmt->execute();
$stmt->bind_result($message_id);

$message_ids = [];
while ($stmt->fetch()) {
    $message_ids[] = $message_id;
}
$stmt->close();

// Mark as read
$insert_stmt = $conn->prepare("INSERT IGNORE INTO chat_read_receipts (message_id, user_id) VALUES (?, ?)");

foreach ($message_ids as $msg_id) {
    $insert_stmt->bind_param('ii', $msg_id, $user_id);
    $insert_stmt->execute();
}

$insert_stmt->close();

echo json_encode(['status' => 'success', 'marked' => count($message_ids)]);
