<?php
include '/var/www/html/includes/db.php'; // absoluter Pfad statt ../
$conn->query("
  INSERT INTO balance_history (datum,saldo)
  SELECT CURDATE(), SUM(betrag) FROM transaktionen
  ON DUPLICATE KEY UPDATE saldo=VALUES(saldo)
");
echo "Tagesupdate erfolgreich.\n";
?>
