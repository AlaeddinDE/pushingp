<?php
include '../includes/db.php';
$event = $_POST['name'] ?? 'Event';
$amount = floatval($_POST['amount'] ?? 0);
$teilnehmer = $_POST['teilnehmer'] ?? [];
if ($amount>0 && count($teilnehmer)>0) {
  $anteil = round($amount / count($teilnehmer),2);
  // Auszahlung (Kasse zahlt)
  $stmt=$conn->prepare("INSERT INTO transaktionen (typ,typ_differenziert,betrag,beschreibung) VALUES ('AUSZAHLUNG','ANTEILIG',-?,?)");
  $stmt->bind_param('ds',$amount,$event);
  $stmt->execute();
  // Forderungen
  foreach($teilnehmer as $id){
    $s=$conn->prepare("INSERT INTO transaktionen (typ,typ_differenziert,betrag,mitglied_id,beschreibung) VALUES ('GRUPPENAKTION_ANTEILIG','ANTEILIG',?,?,?)");
    $s->bind_param('dis',$anteil,$id,$event);
    $s->execute();
  }
  echo json_encode(['status'=>'ok','anteil'=>$anteil]);
} else {
  echo json_encode(['status'=>'error']);
}
?>
