<?php
header('Content-Type: application/json'); include '../includes/db.php';
$eid=intval($_GET['event_id']??0); $out=[];
$stmt=$conn->prepare("SELECT m.name, ep.status FROM event_participants ep JOIN mitglieder m ON m.id=ep.mitglied_id WHERE ep.event_id=? ORDER BY m.name");
$stmt->bind_param('i',$eid); $stmt->execute();
$stmt->bind_result($n,$s); while($stmt->fetch()){ $out[]=['name'=>$n,'status'=>$s]; } $stmt->close();
echo json_encode($out);
