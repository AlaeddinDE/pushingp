<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

secure_session_start();

if (!is_admin()) {
    echo json_encode(['status' => 'error', 'error' => 'Nicht autorisiert']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$transaction_id = intval($input['transaction_id'] ?? 0);

if ($transaction_id <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Ungültige Transaktions-ID']);
    exit;
}

// Transaktion löschen
$stmt = $conn->prepare("DELETE FROM transaktionen WHERE id = ?");
$stmt->bind_param('i', $transaction_id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Transaktion gelöscht'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'error' => 'Fehler beim Löschen'
    ]);
}

$stmt->close();
$conn->close();
