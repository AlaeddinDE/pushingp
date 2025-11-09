<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';
secure_session_start();

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$from = $_GET['from'] ?? null;
$to = $_GET['to'] ?? null;

$sql = "SELECT * FROM shifts WHERE 1=1";
if ($from) {
    $sql .= " AND date >= '" . $conn->real_escape_string($from) . "'";
}
if ($to) {
    $sql .= " AND date <= '" . $conn->real_escape_string($to) . "'";
}
$sql .= " ORDER BY date ASC, user_id ASC";

$result = $conn->query($sql);

$shifts = [];
while ($row = $result->fetch_assoc()) {
    $shifts[] = $row;
}

echo json_encode($shifts);
