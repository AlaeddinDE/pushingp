<?php
header('Content-Type: application/json');
include '../includes/db.php';
$from = $_GET['from'] ?? null; $to = $_GET['to'] ?? null;
$sql = "SELECT id,title,datum,start_time,end_time,location,event_status as status,cost,cost_per_person,paid_by,created_by,description FROM events WHERE 1=1";
if($from){ $sql .= " AND datum >= '".$conn->real_escape_string($from)."'"; }
if($to){   $sql .= " AND datum <= '".$conn->real_escape_string($to)."'"; }
$sql .= " ORDER BY datum ASC";
$res = $conn->query($sql);
$out=[]; 
while($r=$res->fetch_assoc()){ $out[]=$r; }
echo json_encode($out);
