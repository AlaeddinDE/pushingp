<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);

$name = trim($_POST['name'] ?? '');
$action = strtolower(trim($_POST['action'] ?? 'lock'));
$reason = trim($_POST['reason'] ?? '');

if ($name === '') {
    json_response(['error' => 'Kein Name übergeben'], 400);
}

$lock = $action !== 'unlock';

if ($lock) {
    $stmt = $mysqli->prepare("UPDATE members SET is_locked = 1, locked_at = NOW(), locked_reason = ? WHERE name = ?");
    if (!$stmt) {
        json_response(['error' => 'Benutzer konnte nicht gesperrt werden'], 500);
    }
    $stmt->bind_param('ss', $reason, $name);
} else {
    $stmt = $mysqli->prepare("UPDATE members SET is_locked = 0, locked_at = NULL, locked_reason = NULL WHERE name = ?");
    if (!$stmt) {
        json_response(['error' => 'Benutzer konnte nicht entsperrt werden'], 500);
    }
    $stmt->bind_param('s', $name);
}

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($affected === 0) {
        json_response(['error' => 'Benutzer nicht gefunden'], 404);
    }
    json_response(['status' => 'ok', 'locked' => $lock]);
}

$stmt->close();
json_response(['error' => $lock ? 'Benutzer konnte nicht gesperrt werden' : 'Benutzer konnte nicht entsperrt werden'], 500);
?>
