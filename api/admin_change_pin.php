<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);

$name = trim($_POST['name'] ?? '');
$pin = trim($_POST['pin'] ?? '');

if (!$name || !$pin || strlen($pin) < 4 || strlen($pin) > 6) {
  json_response(['error' => 'Name und PIN (4-6 Ziffern) erforderlich'], 400);
}

$stmt = $mysqli->prepare("UPDATE members SET pin = ? WHERE name = ?");
$stmt->bind_param("ss", $pin, $name);

if ($stmt->execute()) {
  json_response(['status' => 'ok']);
} else {
  json_response(['error' => 'PIN konnte nicht aktualisiert werden'], 500);
}
$stmt->close();
?>
