<?php
include '/var/www/html/includes/db.php';
$res=$conn->query("SELECT CONCAT(DATE_FORMAT(datum,'%H:%i'),' – ',m.name,' ',LOWER(t.typ),' ',ROUND(t.betrag,2),' €') AS meldung
                   FROM transaktionen t JOIN mitglieder m ON m.id=t.mitglied_id
                   ORDER BY t.id DESC LIMIT 5");
$meldungen=[];
while($r=$res->fetch_assoc()) $meldungen[]=$r['meldung'];
echo implode(' | ', $meldungen);
?>
