<?php
include '../includes/db.php';
$id = intval($_POST['mitglied_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
if($id>0 && $amount>0){
  $stmt = $conn->prepare("INSERT INTO transaktionen (typ,betrag,mitglied_id,beschreibung) VALUES ('EINZAHLUNG',?,?, 'Automatische Verbuchung')");
  $stmt->bind_param('di', $amount, $id);
  $stmt->execute();
  echo json_encode(["status"=>"ok"]);
} else {
  echo json_encode(["status"=>"error"]);
}
?>
