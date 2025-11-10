<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

if (!isset($_POST['event_id']) || !is_numeric($_POST['event_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid event_id']);
    exit;
}

$event_id = (int)$_POST['event_id'];
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

// Check if user is creator or admin
$stmt = $conn->prepare("SELECT created_by FROM events WHERE id = ?");
$stmt->bind_param('i', $event_id);
$stmt->execute();
$stmt->bind_result($created_by);
$stmt->fetch();
$stmt->close();

if (!$is_admin && $created_by !== $user_id) {
    echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung']);
    exit;
}

// Delete event participants first
$stmt = $conn->prepare("DELETE FROM event_participants WHERE event_id = ?");
$stmt->bind_param('i', $event_id);
$stmt->execute();
$stmt->close();

// Delete event
$stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
$stmt->bind_param('i', $event_id);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $success]);
