<?php
session_start(); header('Content-Type: application/json');
if(!isset($_SESSION['mitglied_id'])){ http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
include '../includes/db.php';
$uid = $_SESSION['mitglied_id']; $eid=intval($_POST['event_id']??0);
$stmt=$conn->prepare("UPDATE event_participants SET status='not_going' WHERE event_id=? AND mitglied_id=?");
$stmt->bind_param('ii',$eid,$uid); $ok=$stmt->execute(); $stmt->close();
echo json_encode(['ok'=>$ok]);
