<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';
secure_session_start();

$current_user_id = get_current_user_id();
$user_id = intval($_POST['user_id'] ?? 0);

// Users can edit their own shifts, admins can edit all shifts
if (!is_admin() && $user_id != $current_user_id) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
$date = $_POST['date'] ?? '';
$type = $_POST['type'] ?? '';
$start_time = $_POST['start_time'] ?? '00:00';
$end_time = $_POST['end_time'] ?? '00:00';

if (!$user_id || !$date || !$type) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

// Check if shift exists
$stmt = $conn->prepare("SELECT id FROM shifts WHERE user_id = ? AND date = ?");
$stmt->bind_param('is', $user_id, $date);
$stmt->execute();
$stmt->bind_result($existing_id);
$exists = $stmt->fetch();
$stmt->close();

if ($exists) {
    // Update
    $stmt = $conn->prepare("UPDATE shifts SET type = ?, start_time = ?, end_time = ?, updated_at = NOW() WHERE user_id = ? AND date = ?");
    $stmt->bind_param('ssis', $type, $start_time, $end_time, $user_id, $date);
} else {
    // Insert
    $stmt = $conn->prepare("INSERT INTO shifts (user_id, date, type, start_time, end_time) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issss', $user_id, $date, $type, $start_time, $end_time);
}

if ($stmt->execute()) {
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['ok' => false, 'error' => $stmt->error]);
}

$stmt->close();
