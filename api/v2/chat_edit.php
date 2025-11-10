<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$message_id = intval($_POST['message_id'] ?? 0);
$new_message = $_POST['new_message'] ?? '';

if ($message_id <= 0 || empty($new_message)) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid data']);
    exit;
}

// Check if user owns the message
$stmt = $conn->prepare("SELECT sender_id FROM chat_messages WHERE id = ?");
$stmt->bind_param('i', $message_id);
$stmt->execute();
$stmt->bind_result($sender_id);
$stmt->fetch();
$stmt->close();

if ($sender_id != $user_id) {
    echo json_encode(['status' => 'error', 'error' => 'Not authorized']);
    exit;
}

// Update message
$stmt = $conn->prepare("UPDATE chat_messages SET message = ?, updated_at = NOW() WHERE id = ?");
$stmt->bind_param('si', $new_message, $message_id);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success']);
