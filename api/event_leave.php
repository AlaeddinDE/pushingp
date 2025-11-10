<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
secure_session_start();
require_login();

header('Content-Type: application/json');

$eid = intval($_POST['event_id'] ?? 0);
if(!$eid){ 
    echo json_encode(['ok'=>false, 'error' => 'Missing event_id']); 
    exit; 
}

$mid = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE event_participants SET status='declined' WHERE event_id=? AND mitglied_id=?");
$stmt->bind_param('ii', $eid, $mid);
$success = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $success]);
?>
