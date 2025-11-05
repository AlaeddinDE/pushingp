<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

$type = $_GET['type'] ?? '';
$allowed = ['event', 'announcement'];

if ($type !== '' && !in_array($type, $allowed, true)) {
    json_response(['error' => 'Ungültiger Filter'], 400);
}

if ($type === '') {
    $stmt = $mysqli->prepare("SELECT id, type, title, content, scheduled_for, created_by, created_at FROM admin_board ORDER BY COALESCE(scheduled_for, created_at) ASC, id DESC");
    if (!$stmt) {
        json_response(['error' => 'Statement konnte nicht vorbereitet werden'], 500);
    }
} else {
    $stmt = $mysqli->prepare("SELECT id, type, title, content, scheduled_for, created_by, created_at FROM admin_board WHERE type = ? ORDER BY COALESCE(scheduled_for, created_at) ASC, id DESC");
    if (!$stmt) {
        json_response(['error' => 'Statement konnte nicht vorbereitet werden'], 500);
    }
    $stmt->bind_param('s', $type);
}

$stmt->execute();
$stmt->bind_result($id, $rowType, $title, $content, $scheduled, $createdBy, $createdAt);

$data = [];
while ($stmt->fetch()) {
    $data[] = [
        'id' => $id,
        'type' => $rowType,
        'title' => $title,
        'content' => $content,
        'scheduled_for' => $scheduled,
        'created_by' => $createdBy,
        'created_at' => $createdAt,
    ];
}

$stmt->close();
json_response($data);
