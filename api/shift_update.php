<?php
session_start(); header('Content-Type: application/json');
if(!isset($_SESSION['mitglied_id'])){ http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
include '../includes/db.php';
$uid = $_SESSION['mitglied_id'];

$enabled = intval($_POST['shift_enabled'] ?? 0);
$mode    = $_POST['shift_mode'] ?? 'none';
$st      = $_POST['shift_start'] ?? null;
$en      = $_POST['shift_end'] ?? null;

if($mode==='early'){ $st='06:00:00'; $en='14:00:00';}
if($mode==='late'){  $st='14:00:00'; $en='22:00:00';}
if($mode==='night'){ $st='22:00:00'; $en='06:00:00';}

$stmt = $conn->prepare("UPDATE mitglieder SET shift_enabled=?, shift_mode=?, shift_start=?, shift_end=? WHERE id=?");
$stmt->bind_param('isssi',$enabled,$mode,$st,$en,$uid);
$ok = $stmt->execute(); $stmt->close();

$conn->query("INSERT INTO system_logs (actor_id,action,meta) VALUES ($uid,'shift_update',CONCAT('mode=',".$conn->real_escape_string($mode)."))");

echo json_encode(['ok'=>$ok,'mode'=>$mode,'start'=>$st,'end'=>$en]);
