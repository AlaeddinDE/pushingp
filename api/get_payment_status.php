<?php
include '../includes/db.php';
$today = date('Y-m-d');
$res = $conn->query("SELECT id,name FROM users");
$out = [];
while($m = $res->fetch_assoc()) {
  $id = $m['id']; $name = $m['name'];
  $paid = $conn->query("SELECT SUM(betrag) FROM transaktionen WHERE typ='EINZAHLUNG' AND mitglied_id=$id")->fetch_row()[0] ?? 0;
  $monthsActive = $conn->query("SELECT TIMESTAMPDIFF(MONTH, aktiv_ab, COALESCE(inaktiv_ab, '$today')) FROM users WHERE id=$id")->fetch_row()[0];
  $soll = ($monthsActive ?? 0) * 10;
  $status = '🟢';
  if($paid < $soll) $status = '🟡';
  if($paid + 10 < $soll) $status = '🔴';
  $out[] = ["name"=>$name,"status"=>$status,"soll"=>$soll,"ist"=>$paid];
}
echo json_encode($out);
?>
