<?php include '../includes/layout.php'; ?>
<h2>💰 Gruppenkasse</h2>
<?php
$res=$conn->query("SELECT m.name,ROUND(SUM(t.betrag),2) AS saldo FROM transaktionen t JOIN mitglieder m ON m.id=t.mitglied_id GROUP BY m.name ORDER BY saldo DESC");
?>
<table><tr><th>Name</th><th>Saldo (€)</th></tr>
<?php while($r=$res->fetch_assoc()): ?>
<tr><td><?=$r['name']?></td><td><?=number_format($r['saldo'],2,',','.')?></td></tr>
<?php endwhile; ?>
</table>
<?php include '../includes/footer.php'; ?>
