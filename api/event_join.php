<?php
session_start();
header('Content-Type: application/json');
include '../includes/db.php';

$eid = intval($_POST['event_id'] ?? 0);
if(!$eid || !isset($_SESSION['mitglied_id'])){ http_response_code(403); echo json_encode(['ok'=>false]); exit; }
$mid = $_SESSION['mitglied_id'];

$conn->query("INSERT INTO event_participants (event_id,mitglied_id,status)
              VALUES ($eid,$mid,'coming')
              ON DUPLICATE KEY UPDATE status='coming'");
echo json_encode(['ok'=>true]);
?>
