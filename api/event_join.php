<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/event_xp_hooks.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

$eid = intval($_POST['event_id'] ?? 0);
if(!$eid){ 
    echo json_encode(['ok'=>false, 'error' => 'Missing event_id']); 
    exit; 
}

$mid = $_SESSION['user_id'];

$stmt = $conn->prepare("INSERT INTO event_participants (event_id, mitglied_id, status) VALUES (?, ?, 'coming') ON DUPLICATE KEY UPDATE status='coming'");
$stmt->bind_param('ii', $eid, $mid);
$success = $stmt->execute();
$stmt->close();

// Award XP for event participation
if ($success) {
    event_rsvp_hook($eid, $mid, 'coming');
}

echo json_encode(['ok' => $success]);
?>
