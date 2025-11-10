<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

$user_id = get_current_user_id();

// Generate unique calendar token
$token = bin2hex(random_bytes(32));

$stmt = $conn->prepare("UPDATE users SET calendar_token = ? WHERE id = ?");
$stmt->bind_param('si', $token, $user_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    $feed_url = 'https://' . $_SERVER['HTTP_HOST'] . '/api/calendar_feed.php?token=' . $token;
    echo json_encode(['status' => 'success', 'url' => $feed_url]);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Token konnte nicht generiert werden']);
}
?>
