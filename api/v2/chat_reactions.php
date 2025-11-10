<?php
// Advanced Chat API - Message Reactions
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$message_id = intval($_POST['message_id'] ?? 0);
$emoji = $_POST['emoji'] ?? '';
$action = $_POST['action'] ?? 'add';

if ($message_id <= 0 || empty($emoji)) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid data']);
    exit;
}

// Create reactions table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS chat_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (message_id, user_id, emoji),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

if ($action === 'add') {
    $stmt = $conn->prepare("INSERT IGNORE INTO chat_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $message_id, $user_id, $emoji);
    $stmt->execute();
    $stmt->close();
    
} elseif ($action === 'remove') {
    $stmt = $conn->prepare("DELETE FROM chat_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?");
    $stmt->bind_param('iis', $message_id, $user_id, $emoji);
    $stmt->execute();
    $stmt->close();
}

// Get all reactions for this message
$result = $conn->query("
    SELECT emoji, COUNT(*) as count, GROUP_CONCAT(u.name SEPARATOR ', ') as users
    FROM chat_reactions cr
    JOIN users u ON u.id = cr.user_id
    WHERE cr.message_id = $message_id
    GROUP BY emoji
");

$reactions = [];
while ($row = $result->fetch_assoc()) {
    $reactions[] = $row;
}

echo json_encode(['status' => 'success', 'reactions' => $reactions]);
