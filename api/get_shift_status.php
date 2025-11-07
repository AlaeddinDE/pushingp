<?php
include '../includes/db.php';
$res=$conn->query("SELECT name,schicht_typ AS schicht,schicht_aktiv AS aktiv,DATE_FORMAT(letzte_aktualisierung,'%H:%i') AS zeit FROM mitglieder ORDER BY name");
$data=[];
while($r=$res->fetch_assoc()) $data[]=$r;
echo json_encode($data);
?>
