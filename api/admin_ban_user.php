<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);

$name = trim($_POST['name'] ?? '');
if (!$name) json_response(['error' => 'Kein Name übergeben'], 400);

// Statt DELETE besser einen banned-Status setzen (falls Spalte existiert)
// Falls nicht, löschen wir den User
$stmt = $mysqli->prepare("DELETE FROM members WHERE name = ?");
$stmt->bind_param("s", $name);

if ($stmt->execute()) {
  json_response(['status' => 'ok']);
} else {
  json_response(['error' => 'Benutzer konnte nicht gesperrt werden'], 500);
}
$stmt->close();
?>
