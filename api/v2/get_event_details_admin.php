<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
secure_session_start();
require_login();

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$event_id = intval($_GET['id'] ?? 0);
if (!$event_id) {
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

// Get Event Details
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param('i', $event_id);
$stmt->execute();
$res = $stmt->get_result();
$event = $res->fetch_assoc();
$stmt->close();

if (!$event) {
    echo json_encode(['error' => 'Event not found']);
    exit;
}

// Get Participants
$participants = [];
$stmt = $conn->prepare("
    SELECT ep.*, u.name, u.username 
    FROM event_participants ep 
    JOIN users u ON ep.mitglied_id = u.id 
    WHERE ep.event_id = ?
");
$stmt->bind_param('i', $event_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $participants[] = $row;
}
$stmt->close();

// Get All Users (for adding new ones)
$users = [];
$res = $conn->query("SELECT id, name, username FROM users WHERE status = 'active' ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode([
    'event' => $event,
    'participants' => $participants,
    'users' => $users
]);
