<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/event_xp_hooks.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'error' => 'Invalid method']);
    exit;
}

$event_id = intval($_POST['event_id'] ?? 0);
$status = $_POST['status'] ?? '';
$user_id = get_current_user_id();

if (!$event_id || !in_array($status, ['accepted', 'declined'])) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

// Check if event is in the past
$stmt = $conn->prepare("SELECT datum FROM events WHERE id = ?");
$stmt->bind_param('i', $event_id);
$stmt->execute();
$stmt->bind_result($event_date);
if ($stmt->fetch()) {
    $stmt->close();
    if (strtotime($event_date) < strtotime(date('Y-m-d'))) {
        echo json_encode(['status' => 'error', 'error' => 'Event liegt in der Vergangenheit']);
        exit;
    }
} else {
    $stmt->close();
    echo json_encode(['status' => 'error', 'error' => 'Event nicht gefunden']);
    exit;
}

// Map frontend status to DB status
$db_status = $status;
if ($status === 'accepted') {
    $db_status = 'coming';
}

// Update or Insert participant status
$stmt = $conn->prepare("
    INSERT INTO event_participants (event_id, mitglied_id, status) 
    VALUES (?, ?, ?) 
    ON DUPLICATE KEY UPDATE status = VALUES(status)
");
$stmt->bind_param('iis', $event_id, $user_id, $db_status);

if ($stmt->execute()) {
    // XP Hooks
    if ($db_status === 'coming') {
        if (function_exists('event_rsvp_hook')) {
            event_rsvp_hook($event_id, $user_id, 'coming');
        }
    }
    
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Datenbankfehler']);
}
$stmt->close();
?>