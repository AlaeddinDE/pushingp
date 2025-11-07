<?php include '../includes/layout.php'; ?>
<h2>🎉 Gruppen-Events</h2>

<?php
if(isset($_SESSION['role']) && $_SESSION['role']==='admin' && $_SERVER['REQUEST_METHOD']==='POST'){
  $stmt=$conn->prepare("INSERT INTO events (name,ort,datum) VALUES (?,?,?)");
  $stmt->bind_param('sss',$_POST['name'],$_POST['ort'],$_POST['datum']);
  $stmt->execute();
  echo "<p style='color:#0f0'>Event hinzugefügt!</p>";
}
?>
<?php if(isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
<form method="post" class="card">
  <h3>➕ Neues Event</h3>
  <input name="name" placeholder="Eventname" required>
  <input name="ort" placeholder="Ort" required>
  <input type="date" name="datum" required>
  <button class="btn">Speichern</button>
</form>
<?php endif; ?>

<div class="card">
<table><tr><th>Datum</th><th>Name</th><th>Ort</th></tr>
<?php
$res=$conn->query("SELECT datum,name,ort FROM events ORDER BY datum ASC");
while($r=$res->fetch_assoc()){
  echo "<tr><td>{$r['datum']}</td><td>{$r['name']}</td><td>{$r['ort']}</td></tr>";
}
?>
</table>
</div>
<?php include '../includes/footer.php'; ?>
