<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $sql = "
      SELECT name,
             ROUND(SUM(
               CASE
                 WHEN type='Einzahlung' THEN amount
                 WHEN type='Gutschrift' THEN 0
                 WHEN type IN ('Auszahlung','Schaden','Gruppenaktion') THEN -amount
                 ELSE 0
               END
             ),2) AS balance
      FROM transactions
      GROUP BY name
      ORDER BY name ASC;
    ";
    
    $res = $mysqli->query($sql);
    if (!$res) {
        json_response(['error' => 'Fehler beim Laden der Salden'], 500);
    }
    $data = $res->fetch_all(MYSQLI_ASSOC);
    json_response($data);
} catch (Exception $e) {
    json_response(['error' => 'Datenbankfehler'], 500);
}
?>
