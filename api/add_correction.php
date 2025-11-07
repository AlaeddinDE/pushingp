<?php
include '../includes/db.php';
$rev=intval($_POST['reversal_von']??0);
if($rev>0){
  $old=$conn->query("SELECT betrag,beschreibung FROM transaktionen WHERE id=$rev")->fetch_assoc();
  if($old){
    $betrag=-$old['betrag']; $desc='Korrektur: '.$old['beschreibung'];
    $stmt=$conn->prepare("INSERT INTO transaktionen (typ,typ_differenziert,betrag,reversal_von,beschreibung) VALUES ('KORREKTUR','KORREKTUR',?,?,?)");
    $stmt->bind_param('dis',$betrag,$rev,$desc);
    $stmt->execute();
    echo json_encode(['status'=>'ok']);
  } else echo json_encode(['status'=>'notfound']);
}else echo json_encode(['status'=>'error']);
?>
