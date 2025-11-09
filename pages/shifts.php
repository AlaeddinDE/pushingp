<?php include '../includes/layout.php'; ?>
<h2>⏱ Schichten</h2>
<?php
$uid=$_SESSION['mitglied_id']??0;
if($uid){
  if(isset($_POST['toggle'])){
    $conn->query("INSERT INTO schichten (mitglied_id,aktiv,startzeit) VALUES ($uid,1,NOW())
      ON DUPLICATE KEY UPDATE aktiv=NOT aktiv,startzeit=IF(aktiv=0,NOW(),startzeit)");
  }
  $r=$conn->query("SELECT aktiv FROM schichten WHERE mitglied_id=$uid");
  $aktiv=$r&&$row=$r->fetch_assoc()?$row['aktiv']:0;
  echo "<form method='post'><button class='btn' name='toggle'>".($aktiv?"Schicht beenden":"Schicht starten")."</button></form>";
}
$res=$conn->query("SELECT m.name,s.aktiv,s.startzeit FROM schichten s JOIN users m ON m.id=s.mitglied_id");
?>
<table><tr><th>Name</th><th>Status</th><th>Startzeit</th></tr>
<?php while($r=$res->fetch_assoc()): ?>
<tr>
<td><?=$r['name']?></td>
<td><?=$r['aktiv']?'🟢 aktiv':'🔴 offline'?></td>
<td><?=$r['startzeit']?></td>
</tr>
<?php endwhile; ?>
</table>
<?php include '../includes/footer.php'; ?>
