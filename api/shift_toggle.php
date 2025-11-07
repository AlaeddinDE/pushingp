<?php
include '/var/www/html/includes/db.php';
session_start();
$uid=$_SESSION['mitglied_id']??0;
if(!$uid){http_response_code(403);exit;}
$conn->query("INSERT INTO schichten (mitglied_id,aktiv,startzeit)
              VALUES ($uid,1,NOW())
              ON DUPLICATE KEY UPDATE aktiv=NOT aktiv,startzeit=IF(aktiv=0,NOW(),startzeit)");
echo json_encode(['status'=>'ok']);
?>
