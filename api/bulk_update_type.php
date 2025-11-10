<?php
// API: Bulk Type Update
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];
$type = $data['type'] ?? '';

$valid_types = ['EINZAHLUNG', 'AUSZAHLUNG', 'GRUPPENAKTION', 'GRUPPENAKTION_ANTEILIG', 'SCHADEN', 'GUTSCHRIFT'];

if (empty($ids) || !in_array($type, $valid_types)) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

// Update alle Transaktionen
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "UPDATE transaktionen SET typ = ? WHERE id IN ($placeholders)";

$stmt = $conn->prepare($sql);
$types = str_repeat('i', count($ids));
$stmt->bind_param('s' . $types, $type, ...$ids);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo json_encode([
        'status' => 'success', 
        'message' => "$affected Transaktionen auf $type geändert"
    ]);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Update fehlgeschlagen']);
}
