<?php
header('Content-Type: application/json'); include '../includes/db.php';
$type = $_GET['type'] ?? '';           // EINZAHLUNG|AUSZAHLUNG|GRUPPENAKTION_ANTEILIG|SCHADEN|...
$mid  = intval($_GET['mitglied_id'] ?? 0);
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$off  = intval($_GET['offset'] ?? 0);

$sql = "SELECT t.id,DATE_FORMAT(t.datum,'%Y-%m-%d %H:%i') as datum,m.name,t.typ,t.betrag,t.beschreibung
        FROM transaktionen t LEFT JOIN users m ON m.id=t.mitglied_id WHERE 1=1";
if($type!==''){ $sql .= " AND t.typ='".$conn->real_escape_string($type)."'"; }
if($mid>0){ $sql .= " AND t.mitglied_id=".$mid; }
if($from!==''){ $sql .= " AND DATE(t.datum)>='".$conn->real_escape_string($from)."'"; }
if($to!==''){   $sql .= " AND DATE(t.datum)<='".$conn->real_escape_string($to)."'"; }
$sql .= " ORDER BY t.datum DESC LIMIT 20 OFFSET ".$off;

$res=$conn->query($sql); $out=[];
while($r=$res->fetch_assoc()){ $out[]=$r; }
echo json_encode($out);
