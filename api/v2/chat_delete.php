<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$message_id = intval($_POST['message_id'] ?? 0);

$stmt = $conn->prepare("DELETE FROM chat_messages WHERE id = ? AND sender_id = ?");
$stmt->bind_param('ii', $message_id, $user_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

echo json_encode(['status' => $affected > 0 ? 'success' : 'error']);
