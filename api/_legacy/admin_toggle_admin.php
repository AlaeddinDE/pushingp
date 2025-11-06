<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);

$member_name = trim($_POST['member_name'] ?? '');
$action = trim($_POST['action'] ?? ''); // 'add' oder 'remove'

if (!$member_name || !in_array($action, ['add', 'remove'])) {
  json_response(['error' => 'Ungültige Parameter'], 400);
}

// Hole die PIN des Mitglieds
$stmt = $conn->prepare('SELECT pin FROM members WHERE name = ? LIMIT 1');
$stmt->bind_param('s', $member_name);
$stmt->execute();
$stmt->bind_result($member_pin);
$found = $stmt->fetch();
$stmt->close();

if (!$found || !$member_pin) {
  json_response(['error' => 'Mitglied nicht gefunden'], 404);
}

if ($action === 'add') {
  // Admin-Rechte hinzufügen
  $stmt = $conn->prepare('INSERT INTO admins (pin, member_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE member_name = ?');
  $stmt->bind_param('sss', $member_pin, $member_name, $member_name);
} else {
  // Admin-Rechte entfernen (nur member_name entfernen, PIN bleibt für Rückwärtskompatibilität)
  $stmt = $conn->prepare('UPDATE admins SET member_name = NULL WHERE member_name = ?');
  $stmt->bind_param('s', $member_name);
}

if ($stmt->execute()) {
  json_response(['status' => 'ok', 'message' => $action === 'add' ? 'Admin-Rechte hinzugefügt' : 'Admin-Rechte entfernt']);
} else {
  json_response(['error' => 'Fehler beim Aktualisieren'], 500);
}
$stmt->close();
?>

