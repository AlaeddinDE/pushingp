<?php include '../includes/layout.php'; ?>
<h2>👥 Crew-Mitglieder</h2>
<table><tr><th>Name</th><th>Guthaben (€)</th></tr>
<?php
$res=$conn->query("SELECT m.name,ROUND(SUM(t.betrag),2) AS saldo FROM mitglieder m LEFT JOIN transaktionen t ON m.id=t.mitglied_id GROUP BY m.id ORDER BY m.name");
while($r=$res->fetch_assoc())
  echo "<tr><td>{$r['name']}</td><td>".number_format($r['saldo']??0,2,',','.')."</td></tr>";
?>
</table>
<?php include '../includes/footer.php'; ?>
