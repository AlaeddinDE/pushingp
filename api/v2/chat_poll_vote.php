<?php
header('Content-Type: application/json');
require_once '../../includes/functions.php';
require_once '../../includes/db.php';
secure_session_start();
require_login();

$user_id = get_current_user_id();
$poll_id = intval($_POST['poll_id'] ?? 0);
$option_id = intval($_POST['option_id'] ?? 0);

if ($poll_id <= 0 || $option_id <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

// Check if poll exists and is active
$stmt = $conn->prepare("SELECT is_active FROM chat_polls WHERE id = ?");
$stmt->bind_param('i', $poll_id);
$stmt->execute();
$stmt->bind_result($is_active);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'error', 'error' => 'Poll not found']);
    exit;
}
$stmt->close();

if (!$is_active) {
    echo json_encode(['status' => 'error', 'error' => 'Poll is closed']);
    exit;
}

// Insert or update vote
// First delete existing vote for this poll if any (to allow changing vote)
$stmt = $conn->prepare("DELETE FROM chat_poll_votes WHERE poll_id = ? AND user_id = ?");
$stmt->bind_param('ii', $poll_id, $user_id);
$stmt->execute();
$stmt->close();

// Insert new vote
$stmt = $conn->prepare("INSERT INTO chat_poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)");
$stmt->bind_param('iii', $poll_id, $option_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Database error']);
}
$stmt->close();
