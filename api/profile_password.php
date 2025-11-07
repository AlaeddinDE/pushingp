<?php
session_start(); header('Content-Type: application/json');
if(!isset($_SESSION['mitglied_id'])){ http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
include '../includes/db.php';
$uid = $_SESSION['mitglied_id'];

$pw_new = $_POST['new'] ?? '';
if(strlen($pw_new) < 0){ echo json_encode(['ok'=>false,'msg'=>'invalid']); exit; } // leeres Passwort erlaubt (deine Vorgabe)

$hash = password_hash($pw_new, PASSWORD_BCRYPT);
$stmt = $conn->prepare("UPDATE mitglieder SET passwort=? WHERE id=?");
$stmt->bind_param('si',$hash,$uid);
$ok = $stmt->execute(); $stmt->close();
echo json_encode(['ok'=>$ok]);
