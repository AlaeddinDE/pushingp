<?php include '../includes/layout.php'; ?>
<h2>⚙ Einstellungen</h2>
<?php
$uid=$_SESSION['mitglied_id']??0;
if($uid && $_SERVER['REQUEST_METHOD']==='POST'){
  if(isset($_POST['pw']) && $_POST['pw']!==''){
    $hash=password_hash($_POST['pw'],PASSWORD_BCRYPT);
    $stmt=$conn->prepare("UPDATE mitglieder SET passwort=? WHERE id=?");
    $stmt->bind_param('si',$hash,$uid);
    $stmt->execute();
    echo "<p style='color:#0f0'>Passwort geändert!</p>";
  }
}
?>
<form method="post" class="card">
  <label>Neues Passwort:</label><br>
  <input type="password" name="pw" placeholder="••••••"><br><br>
  <button class="btn">Aktualisieren</button>
</form>
<?php include '../includes/footer.php'; ?>
