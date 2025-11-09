<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$sql = "SELECT date, name, type FROM holidays ORDER BY date";
$result = $conn->query($sql);

$holidays = [];
while ($row = $result->fetch_assoc()) {
    $holidays[] = $row;
}

echo json_encode($holidays);
