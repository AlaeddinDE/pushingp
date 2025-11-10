<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$message_id = intval($_POST['message_id'] ?? 0);
$action = $_POST['action'] ?? 'pin';

$conn->query("CREATE TABLE IF NOT EXISTS chat_pinned_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNIQUE,
    pinned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
)");

if ($action === 'pin') {
    $stmt = $conn->prepare("INSERT IGNORE INTO chat_pinned_messages (message_id) VALUES (?)");
    $stmt->bind_param('i', $message_id);
    $stmt->execute();
} else {
    $stmt = $conn->prepare("DELETE FROM chat_pinned_messages WHERE message_id = ?");
    $stmt->bind_param('i', $message_id);
    $stmt->execute();
}

$stmt->close();
echo json_encode(['status' => 'success']);
