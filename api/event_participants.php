<?php
header('Content-Type: application/json');
include '../includes/db.php';

$eid = intval($_GET['event_id'] ?? 0);
if(!$eid){ echo json_encode([]); exit; }

$q = "SELECT m.name,p.status FROM event_participants p
      JOIN users m ON m.id=p.mitglied_id
      WHERE p.event_id=$eid ORDER BY m.name";
$res = $conn->query($q);
$out=[];
while($r=$res->fetch_assoc()) $out[]=$r;
echo json_encode($out);
?>
