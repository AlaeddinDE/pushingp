<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$query = $_GET['q'] ?? '';
$chat_id = intval($_GET['chat_id'] ?? 0);

if (empty($query)) {
    echo json_encode(['status' => 'success', 'results' => []]);
    exit;
}

$search = "%{$query}%";
$stmt = $conn->prepare("
    SELECT id, message, sender_id, created_at
    FROM chat_messages
    WHERE (
        (sender_id = ? AND receiver_id = ?) OR
        (receiver_id = ? AND sender_id = ?)
    )
    AND message LIKE ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->bind_param('iiiis', $user_id, $chat_id, $user_id, $chat_id, $search);
$stmt->execute();
$stmt->bind_result($id, $message, $sender_id, $created_at);

$results = [];
while ($stmt->fetch()) {
    $results[] = [
        'id' => $id,
        'message' => $message,
        'sender_id' => $sender_id,
        'created_at' => $created_at
    ];
}
$stmt->close();

echo json_encode(['status' => 'success', 'results' => $results]);
