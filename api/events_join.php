<?php
session_start(); header('Content-Type: application/json');
if(!isset($_SESSION['mitglied_id'])){ http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
include '../includes/db.php';
$uid = $_SESSION['mitglied_id']; $eid=intval($_POST['event_id']??0);
if(!$eid){ echo json_encode(['ok'=>false]); exit; }
$stmt=$conn->prepare("INSERT INTO event_participants (event_id,mitglied_id,status) VALUES (?,?, 'going') ON DUPLICATE KEY UPDATE status='going'");
$stmt->bind_param('ii',$eid,$uid); $ok=$stmt->execute(); $stmt->close();
echo json_encode(['ok'=>$ok]);
