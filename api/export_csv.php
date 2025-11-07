<?php
include '../includes/db.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="kasse_export.csv"');
$out=fopen('php://output','w');
fputcsv($out,['ID','Typ','Betrag','Mitglied','Datum','Beschreibung']);
$res=$conn->query("SELECT id,typ,betrag,mitglied_id,DATE_FORMAT(datum,'%Y-%m-%d'),beschreibung FROM transaktionen");
while($r=$res->fetch_row()) fputcsv($out,$r);
fclose($out);
?>
