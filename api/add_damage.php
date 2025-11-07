<?php
include '../includes/db.php';
$id=intval($_POST['mitglied_id']??0);
$amount=floatval($_POST['amount']??0);
$note=$_POST['note']??'Schaden';
if($id>0 && $amount>0){
  $stmt=$conn->prepare("INSERT INTO transaktionen (typ,typ_differenziert,betrag,mitglied_id,beschreibung) VALUES ('SCHADEN','SCHADEN',?,?,?)");
  $stmt->bind_param('dis',$amount,$id,$note);
  $stmt->execute();
  echo json_encode(['status'=>'ok']);
}else echo json_encode(['status'=>'error']);
?>
