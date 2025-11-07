<?php
session_start();
header('Content-Type: application/json');
include '../includes/db.php';

$eid = intval($_POST['event_id'] ?? 0);
if(!$eid || !isset($_SESSION['mitglied_id'])){ http_response_code(403); echo json_encode(['ok'=>false]); exit; }
$mid = $_SESSION['mitglied_id'];

$conn->query("UPDATE event_participants SET status='declined'
              WHERE event_id=$eid AND mitglied_id=$mid");
echo json_encode(['ok'=>true]);
?>
