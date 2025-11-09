<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';
secure_session_start();

if (!is_admin()) {
    echo json_encode(['ok' => false, 'error' => 'Admin only']);
    exit;
}

$user_id = intval($_POST['user_id'] ?? 0);
$date = $_POST['date'] ?? '';

if (!$user_id || !$date) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM shifts WHERE user_id = ? AND date = ?");
$stmt->bind_param('is', $user_id, $date);

if ($stmt->execute()) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => $stmt->error]);
}

$stmt->close();
