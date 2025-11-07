<?php
session_start(); header('Content-Type: application/json');
if(!isset($_SESSION['mitglied_id'])){ http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
include '../includes/db.php';
$uid = $_SESSION['mitglied_id'];

$name   = trim($_POST['name'] ?? '');
$email  = trim($_POST['email'] ?? '');
$avatar = trim($_POST['avatar_url'] ?? '');
$bio    = trim($_POST['bio'] ?? '');
$pflicht= floatval($_POST['pflicht_monatlich'] ?? 10.00);

$stmt = $conn->prepare("UPDATE mitglieder SET name=?, email=?, avatar_url=?, bio=?, pflicht_monatlich=? WHERE id=?");
$stmt->bind_param('ssssdi',$name,$email,$avatar,$bio,$pflicht,$uid);
$ok = $stmt->execute(); $stmt->close();
echo json_encode(['ok'=>$ok]);
