<?php
// API: Bulk Status Update
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/berechne_zahlungsstatus.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];
$status = $data['status'] ?? '';

if (empty($ids) || !in_array($status, ['gebucht', 'storniert'])) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid parameters']);
    exit;
}

// Update alle Transaktionen
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "UPDATE transaktionen SET status = ? WHERE id IN ($placeholders)";

$stmt = $conn->prepare($sql);
$types = str_repeat('i', count($ids));
$stmt->bind_param('s' . $types, $status, ...$ids);

if ($stmt->execute()) {
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    // Zahlungsstatus für alle betroffenen Mitglieder neu berechnen
    $mitglieder = $conn->query("
        SELECT DISTINCT mitglied_id 
        FROM transaktionen 
        WHERE id IN ($placeholders)
        AND mitglied_id IS NOT NULL
    ");
    
    while ($row = $mitglieder->fetch_assoc()) {
        berechneZahlungsstatus($row['mitglied_id']);
    }
    
    echo json_encode([
        'status' => 'success', 
        'message' => "$affected Transaktionen aktualisiert"
    ]);
} else {
    echo json_encode(['status' => 'error', 'error' => 'Update fehlgeschlagen']);
}
