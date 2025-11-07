<?php
include '/var/www/html/includes/db.php';
$conn->query("UPDATE schichten SET aktiv=0 WHERE aktiv=1 AND TIMESTAMPDIFF(HOUR,startzeit,NOW())>24");
echo "Bereinigung abgeschlossen\n";
?>
