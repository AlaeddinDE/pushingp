<?php
session_start(); header('Content-Type: application/json');
if(!isset($_SESSION['mitglied_id'])){ http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit; }
include '../includes/db.php';
$uid = $_SESSION['mitglied_id'];
$stmt = $conn->prepare("SELECT name,email,avatar_url,bio,shift_enabled,shift_mode,shift_start,shift_end,pflicht_monatlich FROM users WHERE id=?");
$stmt->bind_param('i',$uid); $stmt->execute();
$stmt->bind_result($name,$email,$avatar,$bio,$en,$mode,$st,$en2,$pflicht);
if($stmt->fetch()){
  echo json_encode([
    'name'=>$name,'email'=>$email,'avatar_url'=>$avatar,'bio'=>$bio,
    'shift_enabled'=>(int)$en,'shift_mode'=>$mode,'shift_start'=>$st,'shift_end'=>$en2,
    'pflicht_monatlich'=>(float)$pflicht
  ]);
}else{
  http_response_code(404); echo json_encode(['error'=>'notfound']);
}
$stmt->close();
