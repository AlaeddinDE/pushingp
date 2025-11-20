<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
secure_session_start();
require_login();

if (!is_admin()) {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'error' => 'Invalid method']);
    exit;
}

$event_id = intval($_POST['event_id'] ?? 0);
$title = $_POST['title'] ?? '';
$datum = $_POST['datum'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';
$location = $_POST['location'] ?? '';
$description = $_POST['description'] ?? '';
$cost = floatval($_POST['cost'] ?? 0);
$cost_per_person = floatval($_POST['cost_per_person'] ?? 0);
$paid_by = $_POST['paid_by'] ?? 'private';
$event_status = $_POST['event_status'] ?? 'active';

if (!$event_id || !$title || !$datum) {
    echo json_encode(['status' => 'error', 'error' => 'Missing required fields']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE events 
    SET title=?, datum=?, start_time=?, end_time=?, location=?, description=?, cost=?, cost_per_person=?, paid_by=?, event_status=? 
    WHERE id=?
");
$stmt->bind_param('ssssssddssi', $title, $datum, $start_time, $end_time, $location, $description, $cost, $cost_per_person, $paid_by, $event_status, $event_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'error' => $conn->error]);
}
$stmt->close();
