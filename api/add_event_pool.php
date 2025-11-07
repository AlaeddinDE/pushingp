<?php
include '../includes/db.php';
$name = $_POST['name'] ?? 'Unbenannt';
$amount = floatval($_POST['amount'] ?? 0);
if ($amount>0) {
  $stmt=$conn->prepare("INSERT INTO transaktionen (typ,typ_differenziert,betrag,beschreibung) VALUES ('AUSZAHLUNG','POOL',-?,?)");
  $stmt->bind_param('ds',$amount,$name);
  $stmt->execute();
  echo json_encode(['status'=>'ok']);
} else {
  echo json_encode(['status'=>'error']);
}
?>
