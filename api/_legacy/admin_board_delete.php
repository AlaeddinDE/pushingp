<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    json_response(['error' => 'Ungültige ID'], 400);
}

$stmt = $conn->prepare('DELETE FROM admin_board WHERE id = ?');
if (!$stmt) {
    json_response(['error' => 'Statement konnte nicht vorbereitet werden'], 500);
}

$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    json_response(['status' => 'ok']);
}

json_response(['error' => 'Eintrag konnte nicht gelöscht werden'], 500);
