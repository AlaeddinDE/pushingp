<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';
secure_session_start();

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$sql = "SELECT id, username, name FROM users WHERE status = 'active' AND shift_enabled = 1 ORDER BY shift_sort_order ASC, name ASC";
$result = $conn->query($sql);

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode($users);
