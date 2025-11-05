<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!$isAdmin) json_response(['error' => 'Zugriff verweigert'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error' => 'POST required'], 405);

$type = $_POST['type'] ?? '';
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$scheduledRaw = trim($_POST['scheduled_for'] ?? '');

$allowed = ['event', 'announcement'];
if (!in_array($type, $allowed, true)) {
    json_response(['error' => 'Ungültiger Typ'], 400);
}

if ($title === '') {
    json_response(['error' => 'Titel erforderlich'], 400);
}

$scheduled = null;
if ($scheduledRaw !== '') {
    $ts = strtotime($scheduledRaw);
    if ($ts === false) {
        json_response(['error' => 'Ungültiges Datum'], 400);
    }
    $scheduled = date('Y-m-d H:i:s', $ts);
}

$stmt = $mysqli->prepare("INSERT INTO admin_board (type, title, content, scheduled_for, created_by) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    json_response(['error' => 'Statement konnte nicht vorbereitet werden'], 500);
}

$stmt->bind_param('sssss', $type, $title, $content, $scheduled, $user);

if ($stmt->execute()) {
    json_response(['status' => 'ok', 'id' => $stmt->insert_id]);
}

json_response(['error' => 'Eintrag konnte nicht gespeichert werden'], 500);
