<?php
include '../includes/db.php';
$res=$conn->query("
  SELECT DATE(datum) AS tag, SUM(betrag) AS saldo
  FROM transaktionen WHERE status='gebucht'
  GROUP BY DATE(datum) ORDER BY tag ASC
");
$data=[];
$lauf=0;
while($r=$res->fetch_assoc()){
  $lauf+=$r['saldo'];
  $data[]=['datum'=>$r['tag'],'stand'=>round($lauf,2)];
}
echo json_encode($data);
?>
